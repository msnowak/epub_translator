# EPUB Translator

A self-hosted tool for translating EPUB books using a local LLM served by
[Ollama](https://ollama.com/). The backend is a Symfony 8 / API Platform 4
application running on PHP 8.4 (FrankenPHP), backed by PostgreSQL and a
Symfony Messenger worker that runs the translation. The frontend is a Vite +
React + TypeScript single-page app.

You can register an account, upload a book, watch it being translated
paragraph by paragraph, pause and resume the run, and download the result as
an EPUB that opens in a reader. Correcting individual paragraphs is possible
through the API but does not have a screen yet - the three-column editor with
a live chapter preview is the next stage.

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Compose v2)
- An [Ollama](https://ollama.com/) server reachable from the host, with at
  least one model pulled (e.g. `ollama pull llama3.1:8b`)

## Bootstrap

Run these from the repository root, in order.

```bash
# 1. Create your local environment file
cp .env.example .env

# 2. Build and start the stack
docker compose build
docker compose up -d

# 3. Install PHP dependencies (the Dockerfile does not run composer install,
#    and the ./backend bind mount would shadow it anyway)
docker compose exec backend composer install

# 4. Generate the JWT signing keypair (uses JWT_PASSPHRASE from .env)
docker compose exec backend php bin/console lexik:jwt:generate-keypair

# 5. Create the database and run migrations
docker compose exec backend php bin/console doctrine:database:create --if-not-exists
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
```

Until step 3 completes, the `worker` container will crash-loop (it runs the
same image and needs the same vendor/ directory).

`docker compose up -d` also starts the `frontend` container, which installs
its npm dependencies into a named volume on first start. That takes a minute
or two, and the page is not served until it finishes:

```bash
docker compose logs -f frontend
```

The interface is then at `http://localhost:5173`, the API at
`http://localhost:8000/api`, and the OpenAPI docs at
`http://localhost:8000/api/docs` (those docs describe the API Platform
resources only - `/api/me`, `/api/token/refresh`, `/api/ollama/models`, the
preview, the assets and the download endpoint are plain Symfony controllers
and do not appear there; `php bin/console debug:router` lists everything).

## Downloading the translated book

`GET /api/projects/{id}/download` returns the book as `application/epub+zip`.
The file is assembled per request from the stored original plus whatever
translations exist at that moment, so it always reflects the latest manual
corrections and is never cached.

A project can be downloaded as soon as it has been parsed — including while
the translation is still running. Paragraphs that have no translation yet, or
whose translation failed, are written back in the original language, so the
file always opens. Only `parsing` and `failed` projects refuse with `409`.

Chapter text goes back into the book through the same code path the editor
preview uses, so the downloaded file cannot disagree with what the preview
showed. Images, fonts, stylesheets and navigation are copied byte for byte;
the package document has its `dc:language` set to the target language and its
`dc:title` to the project title.

## Exercising the API by hand

The whole path from a new account to a finished file, verified against a real
book. Useful on its own, and it is the flow the frontend has to reproduce.

```bash
# 1. Register (the field is plainPassword, minimum 8 characters)
curl -s -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"email":"me@example.com","plainPassword":"correcthorse"}'

# 2. Log in - the username field is "email", and the answer is {"token": "..."}
curl -s -X POST http://localhost:8000/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"email":"me@example.com","password":"correcthorse"}'

# 3. Upload a book (multipart; title, targetLanguage and ollamaModel are required,
#    sourceLanguage and customPrompt are optional)
curl -s -X POST http://localhost:8000/api/projects \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@/path/to/book.epub" -F "title=A book" \
  -F "targetLanguage=pl" -F "ollamaModel=gemma4:12b" -F "sourceLanguage=en"

# 4. Wait for parsing to finish: status goes parsing -> ready
curl -s -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/projects/$PROJECT

# 5. Start translating
curl -s -X POST http://localhost:8000/api/projects/$PROJECT/start \
  -H "Authorization: Bearer $TOKEN"

# 6. Watch the progress - segmentCounts and totalSegments come with the
#    project, on the collection and on the single project alike
curl -s -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/projects/$PROJECT

# 7. Download at any point after parsing
curl -s -H "Authorization: Bearer $TOKEN" \
  http://localhost:8000/api/projects/$PROJECT/download -o out.epub
```

One thing that bites in practice: on Windows PowerShell, `curl` is an alias for
`Invoke-WebRequest`, so use `curl.exe` or none of the above will parse.

To see what the worker is doing, including why a paragraph was rejected:

```bash
docker compose logs -f worker
```

## Running tests and static analysis

```bash
docker compose exec backend composer test
docker compose exec backend composer stan
```

`composer test` creates/migrates a separate `_test` database automatically.

The frontend has three gates of its own. They run against mocked HTTP (MSW)
and never touch a running backend:

```bash
docker compose exec frontend npm run test
docker compose exec frontend npm run typecheck
docker compose exec frontend npm run lint
```

## Troubleshooting

**Logging in works, but reloading the page throws you back to the login
screen.** The access token lives in memory only, so after a reload the app
restores the session from the `refresh_token` cookie - and that cookie is
`SameSite=Lax`, for which `localhost` and `127.0.0.1` are two different sites.
Open the interface and point `VITE_API_URL` at the *same* hostname: with the
SPA on `http://localhost:5173`, the API has to be `http://localhost:8000`, not
`http://127.0.0.1:8000`. The browser silently drops the cookie otherwise, and
the only symptom is a `401` from `/api/token/refresh`.

**Ollama is unreachable from the container.** `OLLAMA_BASE_URL` in `.env`
defaults to `http://host.docker.internal:11434`, but by default Ollama binds
to `127.0.0.1`, which is only reachable from the host itself, not from inside
a container. Start Ollama with `OLLAMA_HOST=0.0.0.0:11434` (or whatever port
you use) so it listens on an external interface. Diagnose connectivity with:

```bash
docker compose exec backend php bin/console app:ollama:ping
```

This reports the configured address, lists available models on success, and
prints a numbered checklist (server running? external interface? firewall?
correct `OLLAMA_BASE_URL`?) on failure.

**Checking how the model handles a paragraph.** Translation quality and, more
importantly, whether the model preserves the formatting tokens that carry the
EPUB's inline markup, can be probed without the UI:

```bash
docker compose exec backend php bin/console app:translate:try "This is a [1]very important[/1] paragraph." --target=pl
```

It prints the assembled prompt, the raw answer and the validator's verdict.
Add `--source`, `--model` or `--prompt` to vary the request, or `--project` to
borrow the settings of an existing project. A model that keeps dropping tokens
is a model to replace, not a bug to fix: a paragraph whose tokens are gone can
no longer be written back into the book, so the translator retries it
`MAX_TRANSLATION_ATTEMPTS` times and then marks that one paragraph failed.

**The browser reports a CORS error right after a branch switch, a merge or an
edit under `config/`.** Something like *"No 'Access-Control-Allow-Origin'
header is present on the requested resource"* on `/api/token/refresh` or
`/api/login_check`. CORS is almost certainly configured correctly - check it
from inside the container before touching `nelmio_cors.yaml`:

```bash
docker compose exec backend curl -s -D - -o /dev/null -X OPTIONS http://localhost:8000/api/login_check -H "Origin: http://localhost:5173" -H "Access-Control-Request-Method: POST"
```

If that prints `Access-Control-Allow-Origin`, the configuration is fine and you
are looking at the next entry: a request that died mid-rebuild sends no headers
at all, and a response without headers is indistinguishable, from the browser's
point of view, from a server that refuses the origin. `docker compose logs
backend | grep "Maximum execution time"` confirms it. The fix is the same:
warm the cache from the CLI.

**A download or an API call returns a truncated file or an empty 500.** PHP in
the container is capped at `max_execution_time = 30`, and changing anything in
`config/` or `.env` invalidates the compiled container. Rebuilding it takes
around a minute on a Windows bind mount, so the first request after such a
change dies halfway through and drags the following ones with it - each starts
the rebuild from scratch. The symptom is misleading: headers have already been
sent, so the client keeps whatever bytes arrived rather than seeing an error.
Rebuild from the CLI, where no time limit applies:

```bash
docker compose exec backend php bin/console cache:warmup --env=dev
```

**The preview shows text but no images.** Chapter assets are served by
`/api/projects/{id}/assets/{path}`, the one endpoint outside the JWT firewall:
the browser issues those requests itself from inside the preview iframe, so no
Authorization header is attached. They are covered by a short-lived signature
the preview endpoint mints into every rewritten URL. A missing image usually
means the signature expired — reload the chapter — or that the path is absent
from the book's OPF manifest, which the endpoint checks before serving
anything.

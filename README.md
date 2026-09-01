# EPUB Translator

A self-hosted tool for translating EPUB books using a local LLM served by
[Ollama](https://ollama.com/). The backend is a Symfony 8 / API Platform 4
application running on PHP 8.4 (FrankenPHP), backed by PostgreSQL and a
Symfony Messenger worker that runs the translation. The frontend is a Vite +
React + TypeScript single-page app.

You can register an account, upload a book, watch it being translated
paragraph by paragraph, pause and resume the run, correct individual
paragraphs in a three-column editor with a live chapter preview, and download
the result as an EPUB that opens in a reader.

## Interface language

The interface is available in Polish and English, Polish by default. A
switcher sits in the application header, and a second copy sits on the
sign-in and registration screens - those are reached before the app knows who
is logged in, so the header itself is not on screen yet to hold one. The
choice is remembered per browser, in `localStorage` under
`epubTranslator.locale`. On a first visit, with nothing stored yet, it is
guessed from the browser's `navigator.languages`, falling back to Polish if
neither language it lists is one of the two offered.

Whatever is chosen goes out on every API request as an `Accept-Language`
header, and Symfony negotiates the request locale from it against
`enabled_locales` in `framework.yaml`. That covers translated response
content, but not everything a running translation can fail with - and the two
halves of failure get answered differently. An error raised while handling a
request is raised inside that request, with a language to answer in, so the
backend translates it there and then. An error written by the Messenger
worker has no request behind it - it runs detached, in the background - and
so no language to answer in either. It is stored instead as
a machine-readable code plus parameters (`errorCode` and `errorParams` on
`Project` and `Segment`, backed by the `WorkerError` enum), and the frontend
turns that code into a sentence from its own catalog, in whatever language is
currently active.

**Adding a language.** Create `frontend/src/i18n/<code>.ts`, typed as
`Messages`, and export a catalog literal from it - typing it as `Messages`
means a key missing from that literal is a compile error, not a silent
fallback to Polish at runtime. Add the code to `LOCALES`, `CATALOGS` and
`LOCALE_NAMES` in `frontend/src/i18n/`: the latter two are declared as
`Record<Locale, ...>`, so the compiler stops you if you add the code to one
and forget the other. On the backend, add `messages.<code>.yaml` and
`validators.<code>.yaml` under `backend/translations/`, and add the code to
`enabled_locales` in `backend/config/packages/framework.yaml`. From there,
the catalog parity test (`frontend/src/i18n/catalogs.test.ts`) iterates every
registered locale against the Polish catalog and reports the rest of the way:
a key present in Polish and missing from yours, a translated string whose
`{placeholder}` set does not match the Polish original, and - checked against
what `Intl.PluralRules` actually resolves for that locale - a plural entry
missing a category the language needs or carrying one it does not.

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Compose v2)
- An [Ollama](https://ollama.com/) server reachable from the host, with at
  least one model pulled (e.g. `ollama pull llama3.1:8b`)

## Configuring Ollama

The backend and worker containers talk to Ollama over `OLLAMA_BASE_URL`
(`.env`), which defaults to `http://host.docker.internal:11434` rather than
`http://localhost:11434` - from inside a container, `localhost` names the
container itself, not the host machine. Ollama's own default bind,
`127.0.0.1`, only accepts connections from the host it runs on, so it also
has to be started with `OLLAMA_HOST=0.0.0.0:11434` (or whatever port
`OLLAMA_BASE_URL` names) before a container can reach it at all.

The rest of the Ollama configuration, all in `.env`:

- `OLLAMA_DEFAULT_MODEL` - offered as the default in the project wizard; it
  has to already be pulled.
- `OLLAMA_TIMEOUT` - seconds to wait for one translation request before it
  counts as a failed attempt.
- `OLLAMA_TEMPERATURE` - sampling temperature sent on every request.
- `OLLAMA_API_KEY` - bearer token for a proxied or remote Ollama; leave it
  empty for a plain local one.

Verify the whole path with:

```bash
docker compose exec backend php bin/console app:ollama:ping
```

On success it prints the configured address and lists the models Ollama
reports as available. On failure it prints a numbered checklist (server
running? external interface? firewall? correct `OLLAMA_BASE_URL`?) instead of
a bare connection error.

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

`backend` and `worker` build from the same Dockerfile target but are still
two separate images, so `docker compose build backend` alone leaves `worker`
on a stale one - the same applies to `backend-prod`/`worker-prod` in the
production profile. Run `docker compose build` with no service argument to
rebuild both together.

`docker compose up -d` also starts the `frontend` container, which installs
its npm dependencies into a named volume on first start. That takes a minute
or two, and the page is not served until it finishes:

```bash
docker compose logs -f frontend
```

The interface is then at `http://localhost:5173`, the API at
`http://localhost:8000/api`, and the OpenAPI docs at
`http://localhost:8000/api/docs`. Six endpoints - the preview, the assets and
the download endpoint, plus `/api/me`, `/api/token/refresh` and
`/api/ollama/models` - are plain Symfony controllers rather than API Platform
resources, so API Platform cannot discover them on its own; an
`OpenApiFactoryInterface` decorator (`App\OpenApi\UndocumentedRoutesFactory`)
adds them to the document by hand, so `/api/docs` describes the whole API.

## Running the production profile

A built frontend and backend, with no development dependencies - not TLS, a
reverse proxy, or any orchestration; put those in front of it yourself before
exposing it beyond your own machine.

```bash
docker compose --profile prod up -d --build
```

`--profile prod` overrides `COMPOSE_PROFILES` rather than adding to it, which
is exactly what makes this bring up only `db`, `backend-prod`, `worker-prod`
and `frontend-prod` - the `dev` services stay down. It publishes the same
host ports as the development profile (8000 and 5173), so the two profiles
cannot run at the same time; stop one before starting the other. Note also
that `docker compose --profile prod down` stops `db` along with the rest -
expected, since `db` deliberately carries no profile of its own and belongs
to both.

Two steps are deliberately manual: generating the keypair is one-time,
running migrations is whenever one is pending.

```bash
# The JWT signing keypair lives in the jwt_keys named volume, not on disk in
# the image - there is no bind mount in this profile, and the keys must not
# be baked into it.
docker compose --profile prod exec backend-prod php bin/console lexik:jwt:generate-keypair --skip-if-exists

# Migrations are not run automatically on container start: with
# TRANSLATION_WORKERS > 1, every replica starting at once would race to
# apply the same migration.
docker compose --profile prod exec backend-prod php bin/console doctrine:migrations:migrate -n
```

**`REFRESH_COOKIE_SECURE`.** The production profile defaults this to `1`
(`REFRESH_COOKIE_SECURE: ${REFRESH_COOKIE_SECURE:-1}` in `compose.yaml`), so
the refresh-token cookie is only ever sent over TLS - the right default for a
production deployment. Both an absent line and a blank one
(`REFRESH_COOKIE_SECURE=`) fall back safely to `1`; the trap is setting it to
an actual value without meaning to. Serving over plain HTTP under a hostname
other than `localhost` is the one case where the `1` default breaks the app:
set `REFRESH_COOKIE_SECURE=0` in `.env` for it, or the browser silently drops
the cookie and the session ends the moment the access token expires.
Elsewhere, leave the line out or commented, as in `.env.example`.

**`VITE_API_URL` is baked into the frontend image at build time** - Vite
resolves it while bundling, not while the container runs - so changing it
needs `docker compose --profile prod up -d --build` again, not just a
restart.

**`CORS_ALLOW_ORIGIN` must move together with `VITE_API_URL`.** It is the
regex `nelmio_cors.yaml` checks the browser's `Origin` header against
(`backend/.env`, passed through by `compose.yaml`); it defaults to matching
only `localhost`/`127.0.0.1`. Deploy the SPA under a real hostname without
also setting `CORS_ALLOW_ORIGIN` to match it, and every request from the
browser dies at the CORS preflight before it reaches the application. In the
same vein, the refresh-token cookie is `SameSite=Lax` (see
`REFRESH_COOKIE_SECURE` above), so the SPA and the API have to stay
same-site - not just CORS-allowed - or the browser drops the cookie the way
the Troubleshooting entry below describes for `localhost` versus
`127.0.0.1`, except now across two genuinely different hostnames with no
workaround short of putting both behind the same site.

**Before you expose this.** A verbatim `cp .env.example .env` leaves three
values at their placeholder default, and the README above never told you to
change them:

- `APP_SECRET` - not boilerplate. It is Symfony's `%kernel.secret%`, and
  `App\Preview\AssetUrlSigner` (`backend/src/Preview/AssetUrlSigner.php`) is
  autowired with it as the HMAC key that signs `/api/projects/{id}/assets/`
  URLs, the one endpoint deliberately outside the JWT firewall. With the
  published `change-me` value, anyone who learns a project id and an asset
  path can compute a valid signature themselves and pull book content
  without ever authenticating.
- `JWT_PASSPHRASE` - protects the private key `lexik:jwt:generate-keypair`
  writes into the `jwt_keys` volume; leaving it at `change-me` weakens that
  key to a guessable passphrase.
- `POSTGRES_PASSWORD` - the database's own password, published in this repo.

Generate a real value for each and put it in `.env` before the first
`docker compose --profile prod up`:

```bash
openssl rand -hex 32
```

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

## The chapter editor

`/projekty/:id/rozdzialy/:chapterId` opens a three-column view for correcting
one chapter paragraph by paragraph: a chapter list on the left, the chapter's
paragraphs in the middle (virtualized, so a long chapter does not render every
row up front), and a live preview of the whole chapter on the right. A
`?akapit=<segmentId>` query parameter scrolls both the paragraph list and the
preview to one paragraph on load - the failed-paragraphs list in the project
view links here that way.

Editing a paragraph's translation drives two independent debounces, not one:

- **Preview, 400 ms.** After typing stops for 400 ms, the browser expands the
  edited paragraph's `[1]...[/1]` tokens back into inline markup - using a
  TypeScript port of the same detokenizing logic the backend's
  `InlineTokenizer::detokenize()` implements - and swaps just that paragraph's
  node into the preview document. This is quick, but it is a re-implementation
  of one method, not a call into it: nothing keeps the two sides in sync
  automatically if one of them changes.
- **Save, 800 ms.** After typing stops for 800 ms, the row sends
  `PATCH /api/segments/{id}` and shows "Zapisano" once the response comes
  back. Navigating away or unmounting the row while it still has unsaved
  changes - which happens routinely, not just on navigation, since the
  paragraph list is virtualized and unmounts rows that scroll out of view -
  fires one last save immediately instead of waiting out the debounce, and
  goes through the same mutation as an ordinary save, so a quick edit-and-flick
  is not silently lost: the paragraph list's cache still gets the saved text
  even though the row itself is gone by the time the response arrives. If
  that save fails, there is no row left to show the error on, so it is
  written to a per-chapter channel that the page reads as a banner at the
  top of the chapter instead - but only when the row disappeared for some
  other reason than switching chapters (the list is virtualized, so a row
  scrolling out of view unmounts it too). Leaving the chapter is the
  channel's actual trigger, and it writes under the chapter being left,
  which the page has already stopped reading by the time the response
  arrives - that case is a known gap, not yet covered.

A translation that drops a token number or invents one that is not in the
source never reaches the server: the row detects the mismatch, shows a
validation message, and blocks the save until the same set of token numbers
(and their void/paired kind) is back. This mirrors `TranslationValidator`'s
own `tokenKinds()` check server-side, deliberately down to its blind spot: it
is keyed by token number, not by how many times a number repeats, so a
translation that repeats one token a different number of times than the
source passes this guard exactly as it passes the backend. Nesting and
closing order (`[/1]` before its `[1]`, or a `[1]` that never closes) is a
separate rule the backend alone enforces (`assertWellNested()`) - the editor
does not pre-check it, so that class of mistake still reaches
`PATCH /api/segments/{id}` and comes back as an ordinary 422 with the
backend's message, rather than being blocked client-side.

The live preview is an approximation, not a second copy of the export
pipeline, and that is true even before any edit: it exists only between an
edit and the next chapter reload. `App\Epub\ChapterComposer` remains the one
place that decides how a translation renders into the chapter's XHTML - it
produced the document the editor loaded, and it produces the file that
`GET /api/projects/{id}/download` returns. Reloading the chapter (or
downloading the book) always reflects `ChapterComposer`'s rendering, which is
the one that matters; the live preview is a best-effort stand-in for the
fraction of a second before that reload happens.

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

Under the production profile that service is named `worker-prod`:

```bash
docker compose --profile prod logs -f worker-prod
```

Both profiles also start Dozzle, which shows the same streams in a browser with
filtering, search and live tail:

```
http://localhost:9999
```

It is bound to the loopback interface deliberately. Dozzle reads logs through
the Docker socket, which grants control of the daemon regardless of the
read-only mount, so it must not be published beyond localhost.

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

## Test coverage

```bash
docker compose exec backend composer coverage
docker compose exec frontend npm run coverage
```

Both instrument the same test suites `composer test` and `npm run test` run -
`composer coverage` swaps in pcov, `npm run coverage` swaps in v8 - and write
an HTML report to `backend/var/coverage` and `frontend/coverage`
respectively; open `index.html` in either to browse per-file detail. As last
measured on this branch: backend 93.18% lines, 80.95% methods, 43.58%
classes; frontend 94.19% statements, 81.33% branches, 94.44% functions, 94.2%
lines. Neither number is enforced as a gate - there is no CI here to stop a
commit that drops it, so a threshold would only be decorative.

## Known limitations

Each of these is a decision this stage made deliberately, not an oversight:

- **No end-to-end tests in a real browser.** A deliberate scope decision:
  manual walkthroughs substitute for them. That is a real gap, and it has
  cost real defects - a cross-realm `instanceof`, a namespaced `xlink:href`,
  a layout collapse jsdom cannot measure, and fonts named from inside a
  stylesheet - none of which any jsdom or MSW test could have caught.
- **`TranslatedEpubBuilder` builds the whole book in memory and issues one
  segment query per segment.** A large book's export can exceed the request
  time limit.
- **Nothing checks that the model's answer is in the target language.**
  `TranslationValidator` polices the formatting tokens only, not the
  language of the text between them.
- **An export file left on disk survives an interrupted download.** Nothing
  cleans it up if the client disconnects mid-transfer.
- **`InlineTokenizer::detokenize()` and its TypeScript port
  (`frontend/src/features/editor/detokenize.ts`) share no test suite.** A
  behavioral drift between the two would not be caught automatically by
  either side's tests.
- **`GET /api/projects/{id}` reports zero progress counters.** The provider
  that fills `segmentCounts` and `totalSegments` at read time is wired only
  to the collection endpoint, not the single-project one.
- **A bare-string `@import "file.css";` inside a stylesheet is not rewritten.**
  `StylesheetRewriter` only signs addresses inside `url(...)`; a stylesheet
  pulled in this way still fails to load through the preview.

## Troubleshooting

**`docker compose up` starts only the database.** Your `.env` predates the
production profile and has no `COMPOSE_PROFILES` line. Every service except
`db` now belongs to a profile, so with none selected nothing else comes up.
Add `COMPOSE_PROFILES=dev` to `.env` (it is in `.env.example`).

**Logging in works, but reloading the page throws you back to the login
screen.** The access token lives in memory only, so after a reload the app
restores the session from the `refresh_token` cookie - and that cookie is
`SameSite=Lax`, for which `localhost` and `127.0.0.1` are two different sites.
Open the interface and point `VITE_API_URL` at the *same* hostname: with the
SPA on `http://localhost:5173`, the API has to be `http://localhost:8000`, not
`http://127.0.0.1:8000`. The browser silently drops the cookie otherwise, and
the only symptom is a `401` from `/api/token/refresh`.

**Ollama is unreachable from the container.** See "Configuring Ollama" above
for the host/bind-address requirement (`OLLAMA_HOST=0.0.0.0:11434`) and
`app:ollama:ping` for a diagnosis - its checklist covers the same failure
modes this entry used to spell out.

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

**Switching the `prod` profile off and back on fails to start a container**,
with something like:

```
Error response from daemon: failed to set up container networking:
network 6209f434...48f19 not found
```

`docker compose --profile prod down` removes the project's network along with
the containers, but a container from the compose file that is still around —
started under the other profile, or left over from before the `down` — keeps
the old network's id in its own configuration and can't reattach to the new
one that came up under it. Restarting that container is not enough, because
restart reuses the existing (stale) configuration; it has to be rebuilt:

```bash
docker compose up -d --force-recreate <service>
```

`--force-recreate` replaces the container, not its volumes — named volumes
(the database, the JWT keys) are untouched.

## License

MIT - see [LICENSE](LICENSE).

The dependencies are all under permissive licences (MIT, ISC, BSD, Apache-2.0
and, for the Geist font, the SIL Open Font License); nothing in the tree is
copyleft in a way that reaches this code.
[THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md) carries their attribution,
which matters once you publish the built bundle or the images rather than
just running this locally.

One thing the licence does not cover: the model. Ollama and the model weights
are not distributed with this project, and the model you pull comes with its
own terms - `llama3.1:8b`, the default, is under the Llama 3.1 Community
License and not an open-source licence in the OSI sense.

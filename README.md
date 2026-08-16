# EPUB Translator

A self-hosted tool for translating EPUB books using a local LLM served by
[Ollama](https://ollama.com/). The backend is a Symfony 8 / API Platform 4
application running on PHP 8.4 (FrankenPHP), backed by PostgreSQL and a
Symfony Messenger worker for the (upcoming) translation pipeline. This
repository currently covers the foundation stage: user accounts, JWT
authentication with refresh tokens, and connectivity to Ollama. EPUB upload
and translation land in later stages.

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

The API is then available at `http://localhost:8000/api`, with OpenAPI docs
at `http://localhost:8000/api/docs`.

## Running tests and static analysis

```bash
docker compose exec backend composer test
docker compose exec backend composer stan
```

`composer test` creates/migrates a separate `_test` database automatically.

## Troubleshooting

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

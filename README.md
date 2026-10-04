# Bookshelf

A minimalist reading tracker for your phone: your library, reading progress, favourite quotes and statistics.
Laravel REST API + Vue 3 PWA. The product plan lives in [`docs/mvp-plan.md`](docs/mvp-plan.md).

## Requirements

Docker with Compose. Nothing else: PHP, Node and MySQL all run in containers.

## Quick start

```bash
make up
```

Then open <http://localhost:8000>. On the first start the PHP and Node dependencies are installed
automatically, which takes a couple of minutes.

| What                       | Where                                                    |
|----------------------------|----------------------------------------------------------|
| App (SPA + API)            | <http://localhost:8000>                                  |
| API documentation (Scramble) | <http://localhost:8000/docs/api>                       |
| MySQL from the host        | `127.0.0.1:3308`, user/password/database `bookshelf`    |

The app also works at <http://127.0.0.1:8000>; cookies are per origin, so that is handy for a second test user.

If a port is taken, put e.g. `WEB_PORT=8090` or `MYSQL_PORT=3310` into a root-level `.env` file.
On Linux also add `APP_UID=$(id -u)` and `APP_GID=$(id -g)` there so files created in containers belong to you.

## Everyday commands

| Command              | What it does                                              |
|----------------------|-----------------------------------------------------------|
| `make up` / `down`   | start / stop the stack                                    |
| `make test`          | backend (Pest) and frontend (Vitest) tests                |
| `make lint`          | Pint, Larastan, ESLint, vue-tsc                           |
| `make format`        | auto-format PHP with Pint                                 |
| `make migrate`       | run pending migrations                                    |
| `make fresh`         | **destructive**: rebuild the dev database and seed a demo user |
| `make shell`         | shell inside the PHP container                            |

Composer and artisan run inside the container, e.g. `docker compose exec app composer require ...`.

## Repository layout

```
backend/    Laravel API (Sanctum cookie sessions, Fortify headless auth)
frontend/   Vue 3 + TypeScript + Tailwind SPA (Vite)
docker/     Dockerfile, nginx and MySQL init scripts
docs/       Product plan and project documents
```

## Book catalog (Open Library)

Search and "add from the catalog" go through the backend, which asks the public Open Library API, caches answers for
24 hours and limits each reader to 30 searches a minute. The tests never reach the network (`Http::preventStrayRequests`).
To work offline or against a stand-in, set `OPENLIBRARY_BASE_URL` and `OPENLIBRARY_COVERS_URL` in the root `.env`.
Books missing from the catalog (many Ukrainian editions) are added by hand.

## How the pieces fit

- nginx is the single entry point: `/api`, `/docs` and `/up` go to Laravel, everything else to the Vite dev server.
  Because the SPA and the API share one origin, authentication is a plain cookie session (Sanctum SPA mode, no tokens).
- Tests run against MySQL in a separate `bookshelf_testing` database, so they can never touch development data.
- CI (GitHub Actions) runs Pint, Larastan and Pest for the backend and ESLint, vue-tsc, Vitest and a build for the
  frontend on every pull request.

## Conventions

- Code, comments and commit messages are in English; the UI is in Ukrainian (strings live in `frontend/src/i18n`).
- Changes reach `main` through pull requests with green CI.

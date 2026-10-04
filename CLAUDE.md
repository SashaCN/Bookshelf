# Bookshelf

University project (Software Construction course): a reading tracker, Laravel API + Vue 3 PWA.
Product plan and stages: `docs/mvp-plan.md`. Setup and commands: `README.md`.

## Rules for agents

- Code, comments, identifiers and commit messages in English. User-facing UI text in Ukrainian via `frontend/src/i18n/uk.ts`.
- Everything runs in Docker. Run PHP and Node tooling inside containers (`docker compose exec app ...`, `make ...`), not on the host.
- Never read or write `.env*` files; configuration for dev lives in `docker-compose.yml`, for CI in `.github/workflows/ci.yml`.
- Do not `git push`, and do not make network requests to anything except package registries and Docker images, without asking the user first.
- Definition of done for any change: `make lint` and `make test` are green.

## Architecture in one paragraph

nginx serves the SPA and proxies `/api` to Laravel. Auth is Sanctum cookie-session (SPA mode) with Fortify as a headless
backend; all routes are under `/api`. Domain logic lives in Action/Service classes, not controllers. Access to a user's
data is enforced by Policies and answers 404 for foreign resources. Tests use MySQL (`bookshelf_testing`), never SQLite.

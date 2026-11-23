.PHONY: coverage sonar-up sonar-down sonar up down logs migrate fresh shell test test-backend test-frontend lint format stan build

COMPOSE := docker compose
SONAR := docker compose -f docker-compose.sonar.yml
APP := $(COMPOSE) exec app
FRONT := $(COMPOSE) exec frontend

up: ## Start the whole stack (http://localhost:8000)
	$(COMPOSE) up -d --build
	$(APP) php artisan migrate --force

down: ## Stop the stack (data is kept)
	$(COMPOSE) down

logs: ## Follow logs of all services
	$(COMPOSE) logs -f

migrate: ## Run pending database migrations
	$(APP) php artisan migrate

fresh: ## DESTRUCTIVE: recreate the development database and seed demo data
	$(APP) php artisan migrate:fresh --seed

shell: ## Shell in the PHP container
	$(APP) sh

test: test-backend test-frontend ## Run all tests

test-backend:
	$(APP) vendor/bin/pest

test-frontend:
	$(FRONT) npm run test

lint: stan ## Static checks for both apps
	$(APP) vendor/bin/pint --test
	$(FRONT) npm run lint
	$(FRONT) npm run typecheck

format: ## Auto-format PHP code
	$(APP) vendor/bin/pint

stan:
	$(APP) vendor/bin/phpstan analyse --memory-limit=1G --no-progress

build: ## Production build of the SPA
	$(FRONT) npm run build

coverage: ## Backend coverage report (backend/coverage/clover.xml); needs the dev image with pcov (make up rebuilds it)
	$(APP) vendor/bin/pest --coverage-clover=coverage/clover.xml

sonar-up: ## Start the local SonarQube (http://localhost:9002)
	$(SONAR) up -d

sonar-down: ## Stop the local SonarQube (data is kept)
	$(SONAR) down

sonar: coverage ## Analyse the code in the local SonarQube (needs SONAR_TOKEN)
	$(SONAR) run --rm scanner

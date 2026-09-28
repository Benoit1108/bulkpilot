# Developer entry points. Everything runs in Docker: no PHP or Node needed on the host.
.DEFAULT_GOAL := help
SHELL := /bin/bash

# Containers that write into the checkout run with the uid of its owner.
export HOST_UID := $(shell id -u)
export HOST_GID := $(shell id -g)
# Playwright image tag = exact @playwright/test version pinned in pwa/package.json.
export PLAYWRIGHT_VERSION := $(shell sed -n 's/.*"@playwright\/test": "\([0-9.]*\)".*/\1/p' pwa/package.json)

DC_DEV  := docker compose -f docker-compose.yml -f docker-compose.dev.yml
DC_CI   := docker compose -p bulkpilot-ci -f docker-compose.yml -f docker-compose.dev.yml
DC_TEST := docker compose -f docker-compose.yml -f docker-compose.test.yml

# Where QA commands run; `ci-qa` points them at its throwaway stack.
DC  ?= $(DC_DEV)
PHP  = $(DC) exec -T php
PWA  = $(DC) exec -T pwa

# Set UP_FLAGS= to reuse images built beforehand (CI builds them with a layer cache).
UP_FLAGS ?= --build

.PHONY: help
help: ## List available targets
	@grep -hE '^[a-zA-Z_:-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[35m%-18s\033[0m %s\n", $$1, $$2}'

## —— Development ————————————————————————————————————————————————
.PHONY: up down restart logs sh-php sh-pwa composer db-migrate ciqual db-diff cert
up: ## Build and start the dev stack (https://localhost)
	$(DC_DEV) up $(UP_FLAGS) --wait

down: ## Stop the dev stack
	$(DC_DEV) down --remove-orphans

restart: ## Restart PHP (needed after composer changes: vendor/ is not watched)
	$(DC_DEV) restart php

logs: ## Follow logs
	$(DC_DEV) logs -f

composer: ## Run composer as your user, e.g. make composer ARGS="require vendor/pkg" (then make restart)
	$(DC_DEV) exec -u $(HOST_UID):$(HOST_GID) -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer php composer $(ARGS)

sh-php: ## Shell in the PHP container
	$(DC_DEV) exec php bash

sh-pwa: ## Shell in the Nuxt container
	$(DC_DEV) exec pwa sh

db-migrate: ## Run Doctrine migrations
	$(PHP) bin/console doctrine:migrations:migrate --no-interaction

ciqual: ## Import (or refresh) the CIQUAL food table from the official ANSES download
	$(PHP) bin/console nutrition:catalogue:import --no-debug

db-diff: ## Generate a migration from mapping changes
	$(PHP) bin/console doctrine:migrations:diff

cert: ## Copy Caddy's local root CA to ./caddy-root.crt (to trust it on your OS/phone)
	$(DC_DEV) cp php:/data/caddy/pki/authorities/local/root.crt ./caddy-root.crt

## —— API contract ——————————————————————————————————————————————
.PHONY: openapi openapi-check
# Stable business error codes (DomainException::errorCode()), so the PWA test suite can check
# that every one of them has a French message.
ERROR_CODES = grep -rhoE "return '[a-z_]+\.[a-z_.]+';" api/src | sed -E "s/return '(.*)';/\1/" | sort -u \
	| python3 -c 'import json,sys; print(json.dumps([l.strip() for l in sys.stdin if l.strip()], indent=2))'

openapi: ## Export the OpenAPI spec and error codes, regenerate the TypeScript API types
	@# API Platform caches resource and property metadata: a changed DTO would be exported stale.
	$(PHP) bin/console cache:clear -q
	$(PHP) bin/console api:openapi:export > pwa/openapi/openapi.json
	$(ERROR_CODES) > pwa/openapi/error-codes.json
	$(PWA) pnpm api:types

openapi-check: ## Fail if the committed OpenAPI spec or error codes are out of date
	@$(PHP) bin/console api:openapi:export | diff -q - pwa/openapi/openapi.json >/dev/null \
		|| { echo 'pwa/openapi/openapi.json is out of date: run `make openapi`.'; exit 1; }
	@$(ERROR_CODES) | diff -q - pwa/openapi/error-codes.json >/dev/null \
		|| { echo 'pwa/openapi/error-codes.json is out of date: run `make openapi`.'; exit 1; }

## —— Quality (runs against the dev stack) ———————————————————————————
.PHONY: qa composer-validate php-cs php-cs-fix phpstan deptrac schema-validate phpunit pwa-lint pwa-typecheck vitest
qa: composer-validate php-cs phpstan deptrac schema-validate phpunit openapi-check pwa-lint pwa-typecheck vitest ## Every static check and test suite (same list as CI)

composer-validate:
	$(PHP) composer validate --strict

php-cs: ## Check PHP coding standards
	$(PHP) vendor/bin/php-cs-fixer check --diff

php-cs-fix: ## Fix PHP coding standards
	$(PHP) vendor/bin/php-cs-fixer fix

phpstan: ## Static analysis (level max)
	$(PHP) bin/console cache:warmup --env=test -q
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G --no-progress

deptrac: ## Architecture rules (layers + bounded contexts)
	$(PHP) vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress --fail-on-uncovered
	$(PHP) vendor/bin/deptrac analyse --config-file=deptrac.contexts.yaml --no-progress

schema-validate: ## Doctrine mapping ↔ migrations consistency
	$(PHP) bin/console doctrine:database:create --if-not-exists --env=test -q
	$(PHP) bin/console doctrine:migrations:migrate --no-interaction --env=test -q
	$(PHP) bin/console doctrine:schema:validate --env=test

phpunit: ## PHP tests (unit, integration, api) + coverage gate on Domain/Application (95%)
	$(PHP) bin/console doctrine:database:create --if-not-exists --env=test -q
	$(PHP) bin/console doctrine:migrations:migrate --no-interaction --env=test -q
	@# API Platform keeps resource metadata in a cache pool that survives code changes.
	$(PHP) bin/console cache:pool:clear --all --env=test -q
	$(PHP) php -d pcov.enabled=1 vendor/bin/phpunit --coverage-clover var/coverage/clover.xml
	$(PHP) php tests/coverage-gate.php var/coverage/clover.xml 95

pwa-lint: ## ESLint (incl. formatting)
	$(PWA) pnpm lint

pwa-typecheck: ## vue-tsc strict type check
	$(PWA) pnpm typecheck

vitest: ## Frontend unit & component tests, with coverage thresholds
	$(PWA) pnpm test:coverage

## —— End-to-end ————————————————————————————————————————————————
.PHONY: e2e e2e-prod
e2e: ## Playwright against the running dev stack (quick feedback; Nuxt DevTools overlay is present)
	$(DC_DEV) exec -T php bin/console nutrition:catalogue:import tests/Fixtures/ciqual --no-debug -q
	$(DC_DEV) --profile e2e run --rm e2e

e2e-prod: ## Build the production image and run Playwright against it on a throwaway stack
	@trap '$(DC_TEST) down --volumes --remove-orphans' EXIT; \
	$(DC_TEST) up $(UP_FLAGS) --wait php database mailer && \
	$(DC_TEST) exec -T php bin/console nutrition:catalogue:import /fixtures/ciqual -q && \
	CI=1 $(DC_TEST) --profile e2e run --rm e2e

## —— CI ————————————————————————————————————————————————————————
# Same steps as .github/workflows/ci.yml, on throwaway stacks that never touch dev data.
.PHONY: ci ci-qa
ci: ci-qa e2e-prod ## Full pipeline locally: QA on the dev image, then E2E on the prod image

ci-qa: ## `make qa` on an isolated dev stack (random host ports)
	@trap '$(DC_CI) down --volumes --remove-orphans' EXIT; \
	POSTGRES_PORT=0 MAILPIT_PORT=0 HTTP_PORT=0 HTTPS_PORT=0 HTTP3_PORT=0 $(DC_CI) up $(UP_FLAGS) --wait \
		|| { $(DC_CI) logs --tail=80 php; exit 1; } && \
	$(MAKE) --no-print-directory qa DC='$(DC_CI)'

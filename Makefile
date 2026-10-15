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
# The load test's own throwaway stack, so that it can run beside the E2E one.
DC_PERF := docker compose -p bulkpilot-perf -f docker-compose.yml -f docker-compose.test.yml

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
.PHONY: up down restart logs sh-php sh-pwa composer db-migrate demo ciqual db-diff cert
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

demo: ## Seed the demo account (dev only), e.g. make demo ARGS="--years=1" — see docs/DEVELOPMENT.md
	$(PHP) bin/console app:demo:seed --no-debug $(ARGS)

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
ERROR_CODES = grep -rhoE "return '[a-z_]+\.[a-z_.]+';" api/src | sed -E "s/return '(.*)';/\1/" | LC_ALL=C sort -u \
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
qa: composer-validate php-cs phpstan deptrac schema-validate migrations-check phpunit openapi-check acceptance-check pwa-lint pwa-typecheck vitest ## Every static check and test suite (same list as CI)

migrations-check: ## New migrations only expand the schema (expand/contract, tools/check_migrations.py)
	docker run --rm -v "$(CURDIR)":/repo:ro -w /repo python:3.13-alpine@sha256:2dd78ad5cf13a0b68f5134dc49aa9950203a8cf4b7463431b9f3b398287c5059 python3 tools/check_migrations.py

acceptance-check: ## Every test docs/ACCEPTANCE.md cites exists (tools/check_acceptance.py)
	docker run --rm -v "$(CURDIR)":/repo:ro -w /repo python:3.13-alpine@sha256:2dd78ad5cf13a0b68f5134dc49aa9950203a8cf4b7463431b9f3b398287c5059 python3 tools/check_acceptance.py

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

.PHONY: infection acceptance-check
# Mutation testing against the unit suite: every Domain (MSI ≥ 98 %, api/infection.json5), then the
# Coach, Notification and Training use cases (a ratchet, api/infection.application.json5).
INFECTION = php -d pcov.enabled=1 vendor/bin/infection --no-progress --show-mutations=0
infection: ## Mutation testing: every Domain (MSI ≥ 98 %), then the Coach/Notification/Training use cases (ratchet)
	$(PHP) $(INFECTION)
	$(PHP) $(INFECTION) --configuration=infection.application.json5

pwa-lint: ## ESLint (incl. formatting)
	$(PWA) pnpm lint

.PHONY: pwa-lint-fix
pwa-lint-fix: ## ESLint with automatic fixes (formatting, Tailwind class order)
	$(PWA) pnpm lint:fix

pwa-typecheck: ## vue-tsc strict type check
	$(PWA) pnpm typecheck

vitest: ## Frontend unit & component tests, with coverage thresholds
	$(PWA) pnpm test:coverage

## —— End-to-end ————————————————————————————————————————————————
.PHONY: e2e e2e-prod backup-test zap zap-active perf lighthouse
e2e: ## Playwright against the running dev stack (quick feedback; Nuxt DevTools overlay is present)
	$(DC_DEV) exec -T php bin/console nutrition:catalogue:import tests/Fixtures/ciqual --no-debug -q
	$(DC_DEV) --profile e2e run --rm e2e

# The production stack the E2E steps run against (Playwright, backups, ZAP, Lighthouse).
E2E_STACK = $(DC_TEST) up $(UP_FLAGS) --wait php worker database mailer s3 s3-tls && \
	$(DC_TEST) exec -T php bin/console nutrition:catalogue:import /fixtures/ciqual -q

e2e-prod: ## Build the production image and run Playwright against it on a throwaway stack
	@trap '$(DC_TEST) down --volumes --remove-orphans' EXIT; \
	$(E2E_STACK) && \
	CI=1 $(DC_TEST) --profile e2e run --rm e2e

lighthouse: ## On the running E2E stack: Lighthouse (mobile) on key pages, median of 3 runs, PWA installable; reports in pwa/lighthouse-report
	CI=1 PLAYWRIGHT_ARGS=--config=playwright.lighthouse.config.ts $(DC_TEST) --profile e2e run --rm e2e

zap: ## On the running E2E stack: OWASP ZAP baseline of the app and its API (signed in), failing on any unjustified Medium/High alert
	$(DC_TEST) --profile zap run --rm zap

zap-active: ## Outside CI, on a running E2E stack: ZAP active scan of the API (attacks it: never against real data)
	ZAP_ACTIVE=1 $(DC_TEST) --profile zap run --rm zap

backup-test: ## On the running E2E stack: back the database up to its bucket, then restore it and check it (ADR-0031)
	$(DC_TEST) exec -T worker bin/console app:backup:create --no-debug
	# The E2E tests created accounts: a restore without them is no restore.
	$(DC_TEST) exec -T worker bin/console app:backup:verify --min-accounts=1 --no-debug
	# And a backup that cannot be read must fail the check (here: another passphrase).
	@if $(DC_TEST) exec -T -e BACKUP_PASSPHRASE=not-the-passphrase-it-was-made-with-0123456789 worker bin/console app:backup:verify --no-debug -q; then \
		echo 'app:backup:verify accepted a backup it cannot decrypt' >&2; exit 1; \
	else echo 'An unreadable backup is refused, as it should be.'; fi

perf: ## On a throwaway production stack: seed demo accounts with years of data, then k6 (p95 < K6_P95_MS, no error)
	@trap '$(DC_PERF) down --volumes --remove-orphans' EXIT; \
	$(if $(UP_FLAGS),$(DC_DEV) build php &&) \
	{ [ -f api/vendor/autoload.php ] || $(DC_DEV) run --rm --no-deps --entrypoint composer php install --prefer-dist --no-progress --no-interaction -q; } && \
	POSTGRES_PORT=0 MAILPIT_PORT=0 $(DC_PERF) up $(UP_FLAGS) --wait php worker database mailer s3 s3-tls && \
	$(DC_PERF) --profile perf run --rm seed && \
	$(DC_PERF) --profile perf run --rm k6

## —— Production (docs/DEPLOYMENT.md) —————————————————————————————————————
SHELLCHECK := docker run --rm -v "$(CURDIR):/mnt" -w /mnt koalaman/shellcheck:v0.11.0@sha256:61862eba1fcf09a484ebcc6feea46f1782532571a34ed51fedf90dd25f925a8d --source-path=SCRIPTDIR -x
ACTIONLINT := docker run --rm -v "$(CURDIR):/repo" -w /repo rhysd/actionlint:1.7.7@sha256:887a259a5a534f3c4f36cb02dca341673c6089431057242cdc931e9f133147e9 -color=false
# The server over the tailnet. ADMIN: the VM's sudo user; DEPLOY: the deploy user (your own key, a normal shell).
ADMIN ?= ubuntu@bulkpilot
SERVER ?= deploy@bulkpilot
.PHONY: shellcheck deploy-test server-update server-deploy server-logs server-ssh server-status

shellcheck: ## Lint every shell script (server scripts, entrypoint, ZAP, the composite action's steps) and the GitHub workflows
	$(SHELLCHECK) deploy/*.sh deploy/test/*.sh zap/*.sh api/frankenphp/docker-entrypoint.sh
	@# actionlint does not read composite actions: their `run:` blocks are checked here.
	@mkdir -p var && awk '/^ *run: \|/{on=1; ind=-1; next} on{ if (ind<0) { match($$0,/^ */); ind=RLENGTH } if (length($$0) && substr($$0,1,ind) !~ /^ +$$/) { on=0; next } print substr($$0,ind+1) }' .github/actions/server/action.yml | { echo '#!/usr/bin/env bash'; cat; } > var/composite-action.sh
	$(SHELLCHECK) var/composite-action.sh
	@rm -f var/composite-action.sh
	$(ACTIONLINT)

deploy-test: shellcheck ## The server scripts end to end on a throwaway local "server": signature gate, staging before production, rollbacks, restore, backups, prune, the SSH gate
	deploy/test/deploy-test.sh

server-update: ## Install this checkout's deploy/ on the server (scripts, compose file, timers): bootstrap.sh again, as root
	scp -r deploy "$(ADMIN):"
	ssh -t "$(ADMIN)" sudo ./deploy/bootstrap.sh

server-deploy: ## By hand, without GitHub: deploy commit SHA (signed by CI) to ENV, staging first then production
	@test -n "$(SHA)" && test -n "$(ENV)" || { echo 'usage: make server-deploy SHA=<full commit> ENV=staging|production' >&2; exit 1; }
	ssh "$(SERVER)" /opt/bulkpilot/bin/deploy.sh "$(SHA)" "$(ENV)"

server-logs: ## Follow the logs of ENV (production by default) on the server, e.g. make server-logs SERVICE=worker
	ssh -t "$(SERVER)" /opt/bulkpilot/bin/bp.sh $(or $(ENV),production) logs -f --tail=200 $(SERVICE)

server-status: ## What runs on the server: each environment's commit and its services
	ssh "$(SERVER)" 'for env in production staging; do echo "== $$env: $$(cat /opt/bulkpilot/state/$$env.current 2>/dev/null || echo nothing)"; /opt/bulkpilot/bin/bp.sh $$env ps 2>/dev/null; done'

server-ssh: ## A shell on the server as the deploy user
	ssh -t "$(SERVER)"

## —— CI ————————————————————————————————————————————————————————
# Same steps as .github/workflows/ci.yml, on throwaway stacks that never touch dev data. They run one
# after the other: the load test and Lighthouse measure time, which a busy machine would skew (the
# CI runs them as separate jobs, each on its own runner).
.PHONY: ci ci-qa ci-mutation ci-prod
ci: ci-qa ci-mutation ci-prod perf deploy-test ## Full pipeline locally: QA and mutation testing on the dev image, then E2E (+ backups, ZAP, Lighthouse), the load test and the deployment scripts on the prod image

ci-prod: ## On one throwaway production stack: Playwright, then the backup check, OWASP ZAP and Lighthouse
	@trap '$(DC_TEST) down --volumes --remove-orphans' EXIT; \
	$(E2E_STACK) && \
	CI=1 $(DC_TEST) --profile e2e run --rm e2e && \
	$(MAKE) --no-print-directory backup-test && \
	$(MAKE) --no-print-directory zap && \
	$(MAKE) --no-print-directory lighthouse

ci-qa: ## `make qa` on an isolated dev stack (random host ports)
	@trap '$(DC_CI) down --volumes --remove-orphans' EXIT; \
	POSTGRES_PORT=0 MAILPIT_PORT=0 HTTP_PORT=0 HTTPS_PORT=0 HTTP3_PORT=0 $(DC_CI) up $(UP_FLAGS) --wait \
		|| { $(DC_CI) logs --tail=80 php; exit 1; } && \
	$(MAKE) --no-print-directory qa DC='$(DC_CI)'

ci-mutation: ## `make infection` in a one-off php container: the unit suite needs no database, web server or PWA
	$(if $(UP_FLAGS),$(DC_CI) build php &&) \
	$(DC_CI) run --rm --no-deps --entrypoint sh php -c \
		'[ -n "$$(ls -A vendor 2>/dev/null)" ] || composer install --prefer-dist --no-progress --no-interaction -q; \
		$(INFECTION) && $(INFECTION) --configuration=infection.application.json5'

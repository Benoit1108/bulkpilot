# CLAUDE.md

Personal nutrition / training / progression PWA. The functional spec (French "cahier des charges") is the
reference; the architecture is summarised in docs/ARCHITECTURE.md and decisions live in docs/adr/.

## Non-negotiables

- Work phase by phase (README "Avancement"). Implement only the requested scope; analyse the existing code first.
- Backend is DDD/hexagonal per bounded context: `api/src/<Context>/{Domain,Application,Infrastructure,Presentation}`.
  Domain has no Symfony/Doctrine/API Platform dependency (Doctrine mapping is XML in Infrastructure).
  Contexts only depend on `Shared`. Deptrac enforces both — never loosen its rules to make a change pass.
- API Platform resources are Presentation classes; writes go Processor → CommandBus, reads Provider → QueryBus.
  Handlers implement `CommandHandler` / `QueryHandler` / `DomainEventSubscriber` (no framework attributes).
- No business logic in Nuxt. All external services (AI, food DB, email, storage) sit behind interfaces with Fake implementations.
- The Coach never applies a significant change without explicit user validation. Symfony computes, AI only phrases.
- Every personal record belongs to a user and every query is filtered by it.
- FrankenPHP runs in worker mode: no request/user state in service properties.

## Commands (everything runs in Docker, no PHP/Node on the host)

`make up` · `make qa` · `make e2e` · `make ci` · `make openapi` (after changing an API resource) · `make help`

## Definition of Done

Tests written and green, PHPStan max / Deptrac / PHP CS Fixer / ESLint / vue-tsc green, OpenAPI spec and TS types
regenerated, docs updated, E2E green on the production image (`make e2e-prod`). Never skip or weaken a test or rule.

## End of every phase

Run a full multi-agent code review — one reviewer per domain: security, bugs, architecture/DDD, complexity,
frontend, tests (PHPUnit, Vitest, Playwright), UX/UI, accessibility — verify each finding, fix, then rerun `make ci`.

## Conventions

- Code, comments and commit messages in English; docs and UI copy in French.
- Conventional Commits. Doctrine migrations are reviewed and given a description.

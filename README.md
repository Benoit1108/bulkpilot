# BulkPilot

> Nom de travail. Le code métier n'en dépend pas : le nom affiché vient de `APP_DISPLAY_NAME`.

Application PWA personnelle de pilotage nutrition / entraînement / progression :
*je renseigne mes données → l'application les analyse → elle propose une action → je valide ou refuse.*
Le Coach ne modifie jamais un objectif sans validation explicite.

## Démarrage rapide

Prérequis : Docker (Compose v2.24+) et `make`. Aucun PHP ni Node sur la machine hôte.

```bash
make up        # construit et démarre la stack → https://localhost
make qa        # toutes les vérifications statiques et tous les tests
make e2e       # Playwright contre la stack de dev
make e2e-prod  # Playwright contre l'image de production (stack jetable)
make ci        # pipeline complet, comme GitHub Actions (stacks jetables)
make help      # toutes les commandes
```

| URL | Service |
| --- | --- |
| https://localhost | PWA (Nuxt) — Réglages sur `/reglages` |
| https://localhost/api/docs | Documentation OpenAPI (Swagger UI) |
| https://localhost/_profiler | Profiler Symfony |
| http://localhost:8025 | Mailpit (emails de dev, local uniquement) |

Le certificat HTTPS local est émis par Caddy : `make cert` exporte l'autorité à faire approuver par le système ou le téléphone.

## Stack

| Couche | Choix |
| --- | --- |
| Backend | PHP 8.5, Symfony 8.1, API Platform 5, Doctrine ORM 3 (mapping XML), Messenger |
| Serveur | FrankenPHP (worker mode) + Caddy, une seule origine pour l'API et la PWA |
| Base | PostgreSQL 18 |
| Frontend | Nuxt 4 (SPA), Vue 3.5, TypeScript strict, PWA (Workbox) |
| Qualité | PHPStan max, PHP CS Fixer, Deptrac, PHPUnit 13 · ESLint, vue-tsc, Vitest 5, Playwright |

## Organisation

```text
api/     Symfony — src/<BoundedContext>/{Domain,Application,Infrastructure,Presentation}, plus Shared et System
pwa/     Nuxt — app/{pages,components,features,composables,services}
docs/    Architecture, domaine, API, développement, déploiement, tests, ADR
```

## Documentation

- [Architecture](docs/ARCHITECTURE.md) · [Domaine](docs/DOMAIN.md) · [API](docs/API.md)
- [Développement](docs/DEVELOPMENT.md) · [Tests](docs/TESTING.md) · [Déploiement](docs/DEPLOYMENT.md)
- [Sécurité](SECURITY.md) · [Contribuer](CONTRIBUTING.md) · [Décisions (ADR)](docs/adr/)

## Avancement

| Phase | Périmètre | Statut |
| --- | --- | --- |
| 0 | Architecture, Docker, CI, socle DDD, shell PWA | ✅ |
| 1 | Identity : comptes, sessions et appareils, mots de passe, 2FA (TOTP + codes de secours), Google (OIDC), architecture Apple | ✅ |
| 2–10 | Nutrition, Barcode, Training, Progression, Coach, Dashboard, Export/Sécurité, Qualité, Production | — |

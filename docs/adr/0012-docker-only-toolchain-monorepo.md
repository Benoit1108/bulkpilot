# 0012 — Monorepo et outillage 100 % Docker

**Contexte.** Objectif « 0 € et 0 installation » pour démarrer ; même comportement en local et en CI.

**Décision.** Un dépôt `api/` + `pwa/`. Toutes les commandes passent par `make` et Docker Compose :
`docker-compose.dev.yml` (dev), `docker-compose.test.yml` (image de prod en conditions de test, pour l'E2E).
La CI GitHub Actions exécute les mêmes cibles (`make ci-qa`, `make e2e-prod`) ; `make qa` et `make ci-qa`
partagent une seule liste d'étapes. Les conteneurs qui écrivent dans le dépôt tournent avec l'uid de son propriétaire.
Versions : PHP 8.5, Symfony 8.1, Node 24 LTS, pnpm 12, PostgreSQL 18 ; TypeScript 6.0 car typescript-eslint
ne supporte pas encore TypeScript 7.

**Conséquences.** Aucun écart « ça marche chez moi » ; démarrage à froid plus lent (construction des images).

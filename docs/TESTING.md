# Tests

| Niveau | Outil | Emplacement | Environnement |
| --- | --- | --- | --- |
| Unitaire back | PHPUnit (`unit`) | `api/tests/Unit` | PHP pur, sans kernel ni base |
| Intégration back | PHPUnit (`integration`) | `api/tests/Integration` | kernel + PostgreSQL réel (transaction annulée par test, DAMA) ; câblage réel des bus |
| API | PHPUnit (`api`) + `ApiTestCase` | `api/tests/Api` | requêtes HTTP sur le kernel |
| Architecture | Deptrac | `api/deptrac*.yaml` | couches et isolation des contextes |
| Statique | PHPStan niveau max + strict rules + bleeding edge | `api/phpstan.dist.neon` | — |
| Unitaire front | Vitest, projet `unit` | `pwa/test/unit` | Node, sans runtime Nuxt |
| Composants / composables | Vitest, projet `nuxt` + `@nuxt/test-utils` | `pwa/test/nuxt` | happy-dom, runtime Nuxt |
| Types front | vue-tsc strict | — | — |
| E2E | Playwright (mobile Pixel 7 + desktop Chrome) | `pwa/e2e` | **image de production** complète : navigation, réglages, thème avant chargement, en-têtes de sécurité, cache, 404, manifest, hors ligne |

## Seuils de couverture

- Back : couches `Domain` + `Application` ≥ **95 %** des lignes (`api/tests/coverage-gate.php`, dans `make phpunit`).
- Front : global ≥ 80 %, `app/services/**` ≥ 95 % (branches ≥ 90 %), `app/features/**` et `app/composables/**` ≥ 90 %.

## Lancer

```bash
make qa          # tout, sur la stack de dev en cours
make phpunit     # ou: make phpstan / deptrac / php-cs / vitest / pwa-lint / pwa-typecheck
make e2e         # Playwright contre la stack de dev (DevTools Nuxt visibles : peut gêner en mobile)
make e2e-prod    # Playwright contre l'image de prod, stack jetable — c'est ce que fait la CI
make ci          # pipeline complet
```

Options Playwright : `make e2e-prod PLAYWRIGHT_ARGS="--repeat-each=5 --retries=0"` pour traquer l'instabilité.
En CI, un test qui ne passe qu'au retry fait échouer le job (`failOnFlakyTests`).
Playwright tourne dans le service compose `e2e` (profil `e2e`) ; l'image suit la version exacte de `@playwright/test`.

## Règles

- Les règles métier (Coach en particulier) sont testées **sans IA**, en Given / When / Then.
- Aucun test n'est désactivé et aucune règle d'analyse n'est assouplie pour faire passer la CI.
  Une exception PHPStan doit être ciblée (identifiant + chemin + `count`) et commentée.
- Un test instable est un bug : on trouve la cause, on ne l'ignore pas.
- Ordre d'exécution aléatoire (`executionOrder="random"`) : les tests PHP doivent être indépendants.
- Chaque fonctionnalité importante a des critères d'acceptation traduits en tests API et/ou E2E.
- En fin de phase : revue de code multi-agents (sécurité, bugs, architecture, complexité, front, tests, UX/UI),
  correction des constats, puis relance complète de `make ci`.

## Mode worker

- `WorkerModeTest` (API, déterministe) : deux utilisateurs alternent 20 fois sur un même kernel avec seulement le
  `services_resetter` entre les requêtes ; le `TokenStorage` est vide après reset.
- E2E sur l'image de prod : trois utilisateurs alternés sur les workers FrankenPHP, et un login échoué au milieu.
- Isolation : les appareils d'un autre utilisateur sont invisibles et non révocables (404).

## Données de référence

- `api/tests/Fixtures/ciqual` : 12 aliments extraits des vrais fichiers CIQUAL 2020 (windows-1252, CRLF, valeurs « < x » non échappées),
  conservés à l'octet près (`.gitattributes`). Utilisés par les tests d'intégration, d'API et l'E2E
  (`make e2e` / `make e2e-prod` les importent avant de lancer Playwright).
- Les jours du journal sont relatifs à aujourd'hui dans les tests d'API et E2E (le serveur refuse les jours trop éloignés).

## Temps

Les tests d'API qui dépendent du temps (expiration d'une connexion en attente, d'un lien, d'un appareil inactif,
renouvellement du cookie) utilisent `ClockSensitiveTrait::mockTime()` avec `disableReboot()`.

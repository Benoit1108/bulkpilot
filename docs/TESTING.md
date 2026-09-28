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

L'E2E s'exécute sur FrankenPHP en worker mode (image de prod). À ajouter avec l'authentification (phase 1) :

- E2E : deux contextes navigateur alternés 20 fois sur un seul worker — la session de A n'apparaît jamais chez B ;
- E2E : un login échoué n'altère pas la requête suivante ;
- intégration : après `terminate()` + `services_resetter`, le `TokenStorage` est vide ;
- API : un utilisateur ne peut ni lister ni lire les données d'un autre (404).

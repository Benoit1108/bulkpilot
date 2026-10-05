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
| Sécurité | PHPUnit (`api/tests/Api/Security`) + Playwright (`e2e/security.spec.ts`) + OWASP ZAP (`zap/`) | — | matrice d'accès tirée de l'OpenAPI, limiteurs, en-têtes et cookies sur l'image de prod, ZAP passif (app + API connectée) |
| E2E | Playwright (mobile Pixel 7 + desktop Chrome) | `pwa/e2e` | **image de production** complète : navigation, réglages, thème avant chargement, en-têtes de sécurité, cache, 404, manifest, hors ligne ; photos par une passerelle S3 compatible (`versitygw`) derrière TLS, liens présignés compris |
| PWA | Lighthouse 13 (`pwa/lighthouse`) + Chrome (`Page.getInstallabilityErrors`) | `pwa/lighthouse` | **image de production** : application installable, performance ≥ 85, accessibilité ≥ 95, bonnes pratiques ≥ 95 (mobile, médiane de 3 mesures) sur connexion, accueil, journal, séance, progression, Coach |
| Mutation | Infection (`api/infection.json5`, `api/infection.application.json5`) | `api/src/*/Domain`, `api/src/{Coach,Notification,Training}/Application` | suite `unit` contre chaque mutant : MSI ≥ **98 %** sur les Domain ; cliquet à 75 % sur les cas d'usage (mesuré à l'ajout, cible 90 %) (`make infection`, job CI `mutation`) |
| Acceptation | `docs/ACCEPTANCE.md` | — | chaque critère d'une fonctionnalité renvoie au test qui le prouve |
| Supervision | PHPUnit + Vitest | `api/tests/**/System/**`, `pwa/test/unit/monitoring.test.ts` | ce qui part au signalement d'erreurs est nettoyé (SDK Sentry avec un faux transport côté API, rapporteur maison côté PWA), battements, moniteurs de `/api/health?scope=monitoring`, pings Uptime Kuma (docs/MONITORING.md) |
| Responsive | Playwright (`e2e/responsive.spec.ts`) | `pwa/e2e` | **image de production** : chaque page à 320, 390, 768 et 1280 px, compte rempli |

## Seuils de couverture

- Back : couches `Domain` + `Application` ≥ **95 %** des lignes (`api/tests/coverage-gate.php`, dans `make phpunit`).
- Front : global ≥ 80 %, `app/services/**` ≥ 95 % (branches ≥ 90 %), `app/features/**` et `app/composables/**` ≥ 90 %.

## Lancer

```bash
make qa          # tout, sur la stack de dev en cours
make phpunit     # ou: make phpstan / deptrac / php-cs / vitest / pwa-lint / pwa-typecheck
make e2e         # Playwright contre la stack de dev (DevTools Nuxt visibles : peut gêner en mobile)
make e2e-prod    # Playwright seul contre l'image de prod, stack jetable
make ci-prod     # la même stack, puis sauvegarde, ZAP et Lighthouse — c'est ce que fait le job CI e2e
make zap         # seulement ZAP, sur la stack E2E déjà démarrée (rapports JSON dans zap/report/)
make lighthouse  # seulement Lighthouse, sur la stack E2E déjà démarrée (rapports HTML et scores dans pwa/lighthouse-report/)
make infection   # mutation testing des Domain puis des cas d'usage Coach, Notification, Training (quelques minutes ; rapport HTML dans api/var/infection/)
make ci          # pipeline complet, étape par étape (ci-qa, ci-mutation, ci-prod, perf) : la charge et Lighthouse mesurent
                 # du temps, qu'une machine occupée par une autre étape fausserait ; la CI les lance en jobs séparés
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
- Front : un composant monté est démonté après chaque test (`test/nuxt/setup.ts`) ; rien de ce qu'il a enregistré
  (garde du routeur, écouteurs, minuteurs) ne déborde sur le test suivant.
- Sélecteurs : rôle et nom accessibles d'abord (`getByRole`, texte visible), `data-testid` sinon ; jamais une classe CSS
  (les classes sont des utilitaires Tailwind, ADR-0025).
- Taille de l'application : le build de l'image de production échoue au-delà de `pwa/bundle-budget.json` (`pnpm budget`).
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
- Base de produits factice (`FOOD_DATABASE=fake`, dev, tests et E2E) : `3017620422003` pâte à tartiner, `5449000000996` soda,
  `2000000000015` incomplet, `2000000000022` invraisemblable, `2000000000039` panne (503), tout code EAN-13 en `29…` = produit généré
  (l'E2E en tire un nouveau à chaque exécution : les produits confirmés sont partagés), tout autre code valide = inconnu.
- Les jours du journal sont relatifs à aujourd'hui dans les tests d'API et E2E (le serveur refuse les jours trop éloignés).
- Bibliothèque d'exercices : importée par les tests d'API (`TrainingApiTestCase`) et, en E2E, au démarrage du conteneur.

## Performance

- **Budgets SQL** (`tests/Api/Performance/QueryBudgetTest.php`) : sur deux mois d'un compte de démo, chaque lecture
  des écrans principaux, la saisie d'une série, l'analyse du Coach et les vérifications de notifications ont un nombre
  maximal d'instructions SQL (compté par `tests/Support/Database/QueryCounter`, middleware DBAL du seul
  environnement de test). Le budget = le compte mesuré (dont une pour la session de l'appareil) + 2 : un N+1 le
  dépasse aussitôt. Ne l'augmenter qu'avec une raison. Le compte n'est semé qu'une fois par classe (une seule méthode
  de test : l'isolation des tests l'annule après chacune).
- **Index** (`IndexUsageTest`) : les mêmes instructions, et les écritures d'une séance, sont expliquées avec les
  parcours séquentiels désactivés, sur trois comptes ; un parcours séquentiel d'une table qui grandit (journal,
  séances, pesées, aliments perso, recettes…) ou un index parcouru sans condition fait échouer le test.
- **Charge** (`make perf`, lancé par `make ci` et la CI) : pile de production à part, 5 comptes de démo de 3 ans semés
  côte à côte, puis k6 (`k6/scenario.js`) — un lecteur par compte (tableau de bord, analyses lancées à l'ouverture,
  pages, recherche d'aliments) et un écrivain qui ajoute des lignes (pesée, aliment, séries, sous les limites de
  débit) pendant 60 s après une montée de 15 s ; échoue si p95 ≥ `K6_P95_MS` (300 ms en local, 500 en CI) sur
  l'ensemble, le tableau de bord, les pages ou les écritures, si une requête échoue ou si une vérification rate.
  `PERF_DURATION=30s`, `PERF_YEARS=1`, `PERF_ACCOUNTS=2` pour aller plus vite.

## Hors ligne (séances)

- Vitest : magasin IndexedDB (`fake-indexeddb`) et repli mémoire, file d'envoi (ordre, nouvelles tentatives, échecs gardés,
  abandon), session (superset, repos, reprise après rechargement), page de séance.
- E2E sur l'image de prod : une série saisie avec `context.setOffline(true)` reste « en attente », part au retour du réseau,
  la séance survit à un rechargement, puis l'historique et la suggestion de la séance suivante sont vérifiés.
- E2E à froid (`e2e/offline.spec.ts`) : l'application rouverte **sans réseau** (le service worker sert la coquille, le bandeau
  dit « Connexion impossible »), une séance démarrée depuis le plan gardé à l'avance, une série enregistrée, puis envoyée
  au retour du réseau sans rechargement (le compte, inconnu à l'ouverture hors ligne, est redemandé au retour du réseau) ;
  la recherche d'aliments hors ligne dit qu'elle a échoué (l'API n'est jamais mise en cache, ADR-0024).

## PWA (Lighthouse)

`pwa/lighthouse/lighthouse.spec.ts`, avec sa propre configuration Playwright (`playwright.lighthouse.config.ts`, un seul
worker pour ne pas fausser les mesures) : Chromium lancé avec un port de débogage, Lighthouse s'y branche et garde la
session (`disableStorageReset`). Lighthouse 12 a retiré sa catégorie PWA : l'installabilité est demandée à Chrome
(`Page.getInstallabilityErrors` doit être vide). Chaque page est mesurée **trois fois**, la mesure médiane compte (la durée
de blocage suit la charge de la machine de CI). Seuils : performance 0,85, accessibilité 0,95, bonnes pratiques 0,95
(profil mobile, réseau et processeur simulés). Les rapports HTML de la mesure médiane et `scores.json` sont dans
`pwa/lighthouse-report/` (artefact `lighthouse-report` en CI).

Les chargements gardent la place de ce qui arrive (un widget garde une hauteur proche de la sienne, la journée du journal
et le résumé du poids gardent l'écran) : la liste qui suit ne saute pas quand les données arrivent (décalage de mise en page).

## Responsive

`pwa/e2e/responsive.spec.ts` (projet desktop seulement, chaque test fixe sa taille) visite **toutes les pages** à 320, 390,
768 et 1280 px (tactile jusqu'à 768), sur un compte qui a tout utilisé (`e2e/support/seed.ts` : objectifs, journal, aliment
perso, recette, pesées, objectif de poids, mensurations, programme, séances) et déconnecté pour les pages d'accès.
`e2e/support/layout.ts` vérifie :

- aucun défilement horizontal ;
- aucun élément interactif qui en chevauche un autre ; une fois en bas de page, aucun caché sous un élément fixe (le « + »,
  la barre du bas) ;
- en tactile, des cibles d'au moins 44 × 44 px (le libellé compte avec sa case ; exemptés : liens dans une phrase,
  `display: inline`, et ce qui porte `data-tap-exempt` avec sa raison) ;
- le titre de la page jamais coupé au milieu d'un mot sans trait d'union.

Ce qui n'est pas rendu (contenu d'un `<details>` fermé) ou hors de la page jusqu'au focus (lien d'évitement) est ignoré.
Pas de captures comparées pixel par pixel : trop fragiles pour ce qu'elles apportent.

## Temps

Les tests d'API qui dépendent du temps (expiration d'une connexion en attente, d'un lien, d'un appareil inactif,
renouvellement du cookie) utilisent `ClockSensitiveTrait::mockTime()` avec `disableReboot()`.

## Sécurité

- **Matrice d'accès** (`AccessMatrixTest`) : lit la spec OpenAPI du kernel. Toute opération est soit publique (liste
  `PUBLIC`, avec la raison), soit refusée déconnectée (401) ; toute écriture venue d'un autre site est refusée (403) ;
  toute opération qui nomme une ressource dans son chemin doit dire laquelle (`OWNED`, 404 pour celle d'un autre) ou
  pourquoi le chemin désigne l'appelant (`SCOPED_TO_THE_CALLER`, un jour, dont le contenu est vérifié : celui de
  l'appelant). Toute opération qui prend des identifiants dans sa requête ou son corps (un aliment dans une recette,
  un exercice dans un programme…) dit comment un autre utilisateur les envoie (`IDS_IN_QUERY_OR_BODY`) : refusé
  (404 ou 422), rien de la ressource montré. Chaque liste, synthèse ou recherche lue par un autre utilisateur ne
  contient aucun identifiant ni nom d'Alex. Une nouvelle route fait échouer le test tant qu'elle n'est pas classée ;
  un contrôleur hors spec sous `/api` aussi (`OUTSIDE_THE_SPEC`), et une opération d'API Platform cachée de la spec
  (`openapi: false`). Après les essais d'un autre utilisateur, tout ce qu'Alex voit est inchangé.
- **ZAP** : `zap/scan.sh` crée un compte qui possède une ressource de chaque sorte, passe le baseline sur l'app puis
  l'analyse passive de l'API à partir de la spec (convertie en OpenAPI 3.0, que ZAP lit ; ses chemins nomment ces
  ressources, pour que les requêtes atteignent les cas d'usage au lieu de répondre 404), cookie de session et `Origin`
  de l'app ajoutés à chaque requête (sans quoi les écritures sont refusées avant tout), sans les opérations qui
  fermeraient cette session. `zap/check-alerts.py` échoue sur toute alerte moyenne ou haute restante, et si moins de
  60 % des opérations ont répondu sans erreur client (l'analyse n'aurait rien vu) ; une alerte ne s'accepte qu'en
  `IGNORE` justifié dans `zap/rules.tsv`. Rapports, journal et spec analysée dans `zap/report/` (ignoré par git,
  envoyé en artefact par la CI). `make zap-active` lance l'analyse **active** (attaques) : hors CI, jamais contre de
  vraies données. ZAP parle à la stack sous le nom `app.e2e.test` (son client TLS n'envoie pas de SNI pour un nom
  sans point).

## Mutation (Infection)

- **Ce qui est muté** : tout `src/*/Domain` (les règles métier, Coach compris), contre la suite `unit` seule : rapide,
  sans base ni kernel. `minMsi` et `minCoveredMsi` sont à **98 %** (99 % à l'introduction) : un mutant qui survit est
  un test qui manque ou qui ne vérifie rien. Puis les cas d'usage qui décident (`src/{Coach,Notification,Training}/Application` :
  une analyse par jour, les propositions, les vérifications, la planification), avec leurs doubles en mémoire
  (`api/infection.application.json5`) : un **cliquet** à 75 %, le score mesuré à leur ajout, à remonter à mesure
  qu'on tue leurs survivants (cible 90 %), jamais à baisser.
- **Un survivant se traite** :
  - d'abord par un test au bon bord (« la limite est acceptée, un cran au-delà est refusé », valeurs exactes plutôt
    qu'approchées, longueurs en caractères) ;
  - ou en simplifiant le code quand le mutant ne change rien (un `array_values` que `usort` rend inutile, une
    condition redondante) ;
  - en dernier recours, en l'excluant dans `infection.json5`, **par méthode ou par ligne, avec sa raison en
    commentaire** : mutants équivalents (convergence numérique du Meal Builder, origine des jours d'une moyenne
    glissante, égalités impossibles) ou colonnes que seules les requêtes lisent (clés de recherche, première date
    de suppression), vérifiées par la suite d'intégration que cette passe n'exécute pas. Jamais une classe entière,
    jamais une expression régulière (elle exclurait sans bruit une future ligne semblable ailleurs) ; jamais non plus
    de code tordu pour tuer un mutant équivalent : on garde le code lisible et on l'exclut, avec sa raison.
- `make infection` sur la stack de dev ; `make ci-mutation` dans un conteneur php jetable, sans base ni PWA (job CI
  `mutation`). Les mutants qui
  survivent sont listés dans `api/var/infection/infection.log` et dans le rapport HTML à côté.

## Critères d'acceptation

`docs/ACCEPTANCE.md` liste, par fonctionnalité, ses critères du point de vue de l'utilisateur et le test qui prouve
chacun (`fichier::test` pour PHPUnit, titre du test pour Vitest et Playwright). Il se met à jour dans le commit qui
ajoute ou change la fonctionnalité ; un critère sans test est un test à écrire. La revue de fin de phase (domaine
« tests ») le vérifie.


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
make e2e-prod  # Playwright contre l'image de production (stack jetable) ; make ci-prod ajoute sauvegarde, ZAP, Lighthouse
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
- [Développement](docs/DEVELOPMENT.md) · [Tests](docs/TESTING.md) · [Critères d’acceptation](docs/ACCEPTANCE.md) · [Déploiement](docs/DEPLOYMENT.md) · [Supervision](docs/MONITORING.md) · [Charte graphique](docs/DESIGN.md)
- [Sécurité](SECURITY.md) · [Contribuer](CONTRIBUTING.md) · [Décisions (ADR)](docs/adr/)

## Avancement

| Phase | Périmètre | Statut |
| --- | --- | --- |
| 0 | Architecture, Docker, CI, socle DDD, shell PWA | ✅ |
| 1 | Identity : comptes, sessions et appareils, mots de passe, 2FA (TOTP + codes de secours), Google (OIDC), architecture Apple | ✅ |
| 2 | Nutrition : base CIQUAL, aliments personnels, journal par repas, objectifs (estimation détaillée, historique), répartition, moyennes 7/30 j, recettes et portions, repas favoris, Meal Builder | ✅ |
| 3 | Barcode : scan caméra (natif + WASM) ou saisie, Open Food Facts derrière Symfony, confirmation ou correction, catalogue partagé | ✅ |
| 4 | Training : bibliothèque d’exercices et exercices perso, programmes versionnés (supersets, drop sets, tempo, RIR), séances, minuteur, progression, PR | ✅ |
| 4.5 | Socle front : Tailwind CSS sur nos tokens, composants de 250 lignes au plus, garde « quitter sans enregistrer », erreurs dans l’OpenAPI, budget de taille, ADR graphiques et photos | ✅ |
| 5 | Progression : pesées (moyennes 7/30 j, tendance), objectif et jalons avec estimation, mensurations et mesures perso, photos privées (sessions, comparaison), graphiques, calories face au poids, statistiques d’entraînement | ✅ |
| 6 | Coach : règles déterministes (calories, protéines, journal, pesées, séances, stagnation, records), 3 recommandations expliquées à valider, historique, adhérence, rapport hebdomadaire, personnalité, fournisseur IA abstrait (gabarits) | ✅ |
| 7 | Dashboard : widgets personnalisables (jour, semaine, mois), Quick Actions, recherche ⌘K, notifications dans l’app (catégories, horaires silencieux), analyse nocturne du Coach | ✅ |
| 8 | Export / sécurité : exports (JSON, CSV, Excel, PDF, photos), suppression du compte après 7 jours, journal d’activité, sauvegardes chiffrées vérifiées | ✅ |
| 9 | Qualité : matrice d’accès et scan ZAP, budgets SQL, index et charge (k6), Lighthouse, hors ligne, gabarits responsive, mutation testing, données de démo, critères d’acceptation | ✅ |
| 10 | Production (gratuite au départ) : VM Oracle Always Free derrière Tailscale, image construite une fois par la CI, testée puis signée (GHCR), PostgreSQL sur la VM et sauvegardes chiffrées, Cloudflare R2, Brevo, staging automatique puis production approuvée à la main avec retour arrière automatique, Sentry, Uptime Kuma et healthchecks.io | ✅ |
| R0 | Refonte UX/UI (ADR-0034) : corrections rapides — alignements, listes, décimales à la française, graphiques hebdomadaires sans la semaine en cours, vrais titres de page | ✅ |
| R1 | Charte graphique à la manière d’Apple Santé (docs/DESIGN.md), boutons en pilule, contrôle segmenté, cartes et listes groupées, barre latérale à icônes et pages de section, contenu pleine largeur, thème de l’appareil par défaut | ✅ |
| R2 | Accueil façon Apple Santé : anneaux calories et macros, macros en couleur, tuiles poids (mini-courbe), séances et records (barres), objectif, séance du jour, cartes entières cliquables, tuiles personnalisables | ✅ |
| R3 | Entraînement : dessins Everkinetic des exercices (images wger pour quelques-uns), silhouette des muscles, exercices rangés par muscle, jour conseillé, calendrier des séances du mois, programme en colonnes, son en fin de repos, page Crédits | ✅ |
| R4 | Nutrition : bande de semaine avec les jours saisis, vraie date du jour, bilan avec anneau et macros en couleur, repas en listes groupées (un aliment par ligne, « + » par repas), calories de la semaine en barres, colonne de synthèse sur PC, panneau d’ajout en onglets (Récents, Favoris, Recettes) avec « + » rapide à la dernière quantité et scan dans le champ, historique des objectifs en frise | ✅ |
| R5 | Progression : tableau de bord (poids en tête avec tendance et courbe, objectif, tuiles calories, protéines, tonnage et mensuration, séries par muscle de la semaine), pesée dans un panneau, analyses en grille avec le volume par muscle, pesées par mois avec l’écart, mini-courbes des mensurations, sélecteurs segmentés sur les photos | ✅ |
| R6 | Coach (icônes de priorité, un seul bouton plein, adhérence en anneau et points, liste groupée des sous-pages), notifications avec une icône par catégorie, réglages en listes groupées et déconnexion à part, thème clair vérifié | ✅ |

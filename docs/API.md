# API

- REST + JSON (`application/json`) ou JSON-LD/Hydra (`application/ld+json`), exposée sous `/api`.
- Documentation OpenAPI générée par API Platform : https://localhost/api/docs (Swagger UI en dev uniquement),
  spec brute : `/api/docs.jsonopenapi`.
- La spec est versionnée dans `pwa/openapi/openapi.json` ; `make openapi` la régénère avec les types TypeScript
  (`pwa/app/types/api.d.ts`). La CI échoue si elle n'est pas à jour.

## Conventions

| Sujet | Règle |
| --- | --- |
| Routes | Déduites des use cases, pas d'un CRUD généré : `POST /api/workouts/{id}/finish` plutôt qu'un `PATCH` générique. |
| Ressources | Classes de `Presentation/Api`, jamais les entités du domaine. |
| Écritures | Processor → `CommandBus` ; lectures : Provider → `QueryBus`. |
| Erreurs | `application/problem+json` (RFC 9457) avec un `code` stable pour les erreurs métier ; jamais de stack trace hors dev. |
| Validation | Contraintes sur les DTO d'entrée → `422` avec `violations[]`. Un nombre obligatoire **omis** est refusé sur son champ (jamais lu comme 0) ; `null` explicite sur un champ obligatoire → `400`. |
| Pagination | Hydra (`hydra:view`) ; taille par défaut 30. |
| Identifiants | UUID dans les IRIs. |
| Authentification | Cookie de session httpOnly, `SameSite=Lax`, même origine (phase 1, ADR-0008) — les opérations ne sont donc pas `stateless`. |
| Traçabilité | Chaque réponse porte `X-Request-Id` (posé par Caddy), repris dans les erreurs côté PWA. |
| Erreurs métier | Les `DomainException` deviennent du problem+json avec leur `code` stable (`identity.invalid_token`…) ; statut selon la catégorie : `NotFound` 404, `Conflict` 409, `AuthenticationFailed` 401, `Unavailable` 503 (service externe injoignable), autre règle 422. La PWA traduit le `code`. Les routes Training déclarent dans l'OpenAPI les erreurs qu'elles peuvent renvoyer (404, 409, 429…), avec ce corps (`ProblemResponses`) ; Identity et Nutrition le feront (limite connue). |
| CSRF | Requête mutante refusée (403) si `Origin` ≠ l'hôte ou `Sec-Fetch-Site` indique un autre site. |
| Limitation | 429 + `Retry-After` : connexion (5 / 15 min par IP+email, 30 / 15 min par IP, 50 / h par compte), inscription (5 / h par IP), liens email (10 / h par IP et par adresse), second facteur (5 / 15 min par compte), ressaisie du mot de passe (10 / 15 min par compte), écritures Training (600 / min par compte). Valeurs par variables `RATE_LIMIT_*`. |
| Jours | Un jour calendaire est `YYYY-MM-DD` (jour **local** de l'utilisateur, envoyé par la PWA) ; accepté jusqu'à demain (fuseaux en avance) et sur 10 ans d'historique. |
| Concurrence | 409 `shared.concurrent_update` quand deux requêtes modifient la même chose en même temps. |

## Endpoints

| Méthode | Route | Description | Auth |
| --- | --- | --- | --- |
| POST | `/api/auth/register` | Crée le compte (si `REGISTRATION_ENABLED`), envoie le lien de vérification, connecte l'appareil → `201 Account` | publique |
| POST | `/api/auth/sign-in` | Email + mot de passe → `{twoFactorRequired: false, account}` ou `{twoFactorRequired: true, account: null}` | publique |
| POST | `/api/auth/sign-in/second-factor` | Code de l'application ou code de secours `{code}` (5 essais / 15 min par compte) → `{account}` | publique (connexion en attente) |
| GET | `/api/auth/providers` | Fournisseurs externes activés (`google`…) | publique |
| GET | `/api/auth/oauth/{provider}/start` | Redirection vers le fournisseur (OIDC, PKCE, `state`) ; `?redirect=`, `?login_hint=` | publique |
| GET | `/api/auth/oauth/{provider}/callback` | Retour du fournisseur → redirection vers la PWA (`/`, `/connexion?second_factor=1` ou `?oauth_error=<code>`) | publique |
| POST | `/api/auth/sign-out` | Déconnecte cet appareil (idempotent) → `204` | publique |
| POST | `/api/auth/email-verification` | Valide le lien reçu par email `{token}` → `204` | publique |
| POST | `/api/auth/password-reset-request` | Envoie un lien si un compte **vérifié** existe `{email}` → toujours `202` | publique |
| POST | `/api/auth/password-reset` | Nouveau mot de passe `{token, newPassword}`, déconnecte tous les appareils → `204` | publique |
| GET | `/api/me` | Compte connecté → `Account` | connecté |
| PUT | `/api/me/profile` | Profil (prénom, date de naissance `YYYY-MM-DD` stricte, sexe, taille) → `Account` | connecté |
| POST | `/api/me/email-verification` | Renvoie un lien (l'ancien meurt) → `204` | connecté |
| POST | `/api/me/password` | `{currentPassword, newPassword}`, déconnecte les autres appareils → `204` | connecté |
| POST | `/api/me/two-factor/setup` | `{password}` → `{secret, otpauthUri}` (email vérifié requis) | connecté |
| POST | `/api/me/two-factor/confirm` | `{code}` : active la 2FA → `{recoveryCodes}` (affichés une seule fois) | connecté |
| POST | `/api/me/two-factor/recovery-codes` | `{password}` → nouveaux `{recoveryCodes}` | connecté |
| POST | `/api/me/two-factor/disable` | `{password}` → `204` | connecté |
| GET | `/api/me/sessions` | Appareils connectés (`current` = celui-ci) | connecté |
| DELETE | `/api/me/sessions/{id}` | Déconnecte un appareil (404 si inconnu ou à quelqu'un d'autre) → `204` | connecté |
| GET | `/api/foods?q=&limit=` | Recherche (mots dans n'importe quel ordre, sans accents ni casse, 2 caractères min., 30 résultats max.) : aliments de l'utilisateur, puis noms qui commencent par la requête, puis les plus proches | connecté |
| GET | `/api/foods/recent` | 20 derniers aliments saisis (encore visibles), le plus récent d'abord | connecté |
| GET | `/api/foods/custom` | Aliments personnels, par nom | connecté |
| GET | `/api/foods/{id}` | Un aliment CIQUAL ou personnel (404 si supprimé ou à quelqu'un d'autre) | connecté |
| POST | `/api/foods` | Crée un aliment personnel (valeurs pour 100 g, portions, code-barres facultatif, unique parmi ses aliments → sinon 409) → `201 Food` | connecté |
| PUT | `/api/foods/{id}` | Remplace un aliment personnel ; les entrées déjà saisies gardent leurs valeurs → `Food` | connecté |
| DELETE | `/api/foods/{id}` | Supprime (soft delete) un aliment personnel ; CIQUAL → 422 `nutrition.invalid_food` | connecté |
| GET | `/api/nutrition/days/{day}` | Journal du jour `YYYY-MM-DD` (jour local) : totaux, objectif en vigueur ce jour-là, les 5 repas avec leur part de l'objectif | connecté |
| POST | `/api/nutrition/entries` | `{foodId \| recipeId, day, slot, grams}` (recette : grammes du plat) : copie les valeurs dans l'entrée → `201 NutritionDay` | connecté |
| POST | `/api/nutrition/entries/batch` | `{day, slot, items: [{foodId, grams}]}` (1 à 10, sinon 422 `nutrition.invalid_batch`) : tout ou rien → `201 NutritionDay` | connecté |
| GET | `/api/nutrition/meal-builder?day=&slot=` | Meal Builder : ce qui reste (du jour, ou de la part d'un repas) et jusqu'à 3 repas qui le remplissent → `MealBuilder` (`remaining: null` sans objectif) | connecté |
| PUT | `/api/nutrition/entries/{id}` | `{day, slot, grams}` : corrige ou déplace → le `NutritionDay` d'arrivée | connecté |
| DELETE | `/api/nutrition/entries/{id}` | → `204` | connecté |
| GET | `/api/nutrition/averages?days=7\|30&today=` | Moyennes quotidiennes sur les jours **renseignés** des N jours précédant `today` (exclu), objectif en vigueur | connecté |
| POST | `/api/nutrition/targets/estimate` | Estimation Mifflin-St Jeor détaillée, rien n'est enregistré → `EnergyEstimate` | connecté |
| GET | `/api/nutrition/targets` | Historique des objectifs (50 derniers), le plus récent d'abord | connecté |
| POST | `/api/nutrition/targets` | Nouveaux objectifs à partir d'aujourd'hui (ou demain), avec l'estimation d'origine éventuelle → `201 NutritionTarget` | connecté |
| POST | `/api/nutrition/barcode/scan` | `{barcode}` → `{status: known, food}` (déjà ici) ou `{status: found, product}` (proposition Open Food Facts, `missing`, `suspect`, `complete`) ; 404 inconnu, 503 Open Food Facts injoignable ou budget global épuisé, 429 au-delà de 30 scans et confirmations / min. N'enregistre rien | connecté |
| POST | `/api/nutrition/barcode/confirm` | `{barcode, corrections?}` : sans corrections, produit partagé (valeurs relues à la source) ; avec, aliment personnel avec son code → `201 Food` (422 `nutrition.incomplete_product` si incomplet sans corrections, `nutrition.invalid_barcode` si le code des corrections diffère, violations `corrections.*` ; 409 code déjà pris par un autre de ses aliments ou confirmation simultanée) | connecté |
| GET | `/api/exercises?q=&muscle=` | Exercices utilisables (bibliothèque + perso, perso d'abord), filtrés par mots du nom et muscle (principal ou secondaire) ; muscle inconnu → 422 | connecté |
| GET | `/api/exercises/{id}` | Un exercice, avec sa consigne personnelle (`note`) et ses valeurs habituelles | connecté |
| POST | `/api/exercises` | Crée un exercice perso (300 au plus) → `201 Exercise` | connecté |
| PUT | `/api/exercises/{id}` | Remplace un exercice perso ; bibliothèque → 422 `training.invalid_exercise` | connecté |
| DELETE | `/api/exercises/{id}` | Suppression douce d'un exercice perso (reste nommé dans les programmes) → `204` | connecté |
| PUT | `/api/exercises/{id}/note` | `{text}` : consigne personnelle, vide = retirée → `Exercise` | connecté |
| GET | `/api/programs` | Programmes, l'actif d'abord, chacun avec sa version courante | connecté |
| GET | `/api/programs/{id}?version=` | Un programme, une version détaillée (la courante par défaut) et la liste des versions | connecté |
| POST | `/api/programs` | `{name, days: [{name, blocks: [{exercises: [...]}]}]}` (50 programmes au plus) ; devient le programme suivi si aucun ne l'est → `201 Program` | connecté |
| PUT | `/api/programs/{id}` | Renomme ; si les jours changent, enregistre la version suivante (500 au plus) → `Program` | connecté |
| POST | `/api/programs/{id}/activate` · `/deactivate` | Programme suivi (un seul à la fois ; course → 409) → `Program` | connecté |
| DELETE | `/api/programs/{id}` | Archive (versions conservées pour les séances passées) → `204` | connecté |
| GET | `/api/exercises/{id}/progress` | Records de l'exercice et un point par séance terminée (charge max, 1RM estimé, volume, reps, durée) | connecté |
| GET | `/api/workouts/preview?programId=&day=` | Plan d'un jour du programme (version courante) avec consigne, dernière fois, suggestion et records de chaque exercice ; gardé par la PWA pour démarrer hors ligne | connecté |
| PUT | `/api/workouts/{id}` | Démarre la séance `{startedAt, programId?, versionNumber?, dayIndex}` (id choisi par l'appareil, idempotent) ; 409 `training.workout_in_progress` si une autre est en cours ; id abandonné ou d'un autre compte → 422 ; le jour garde le nom du programme (`programDay.programName`) → `Workout` | connecté |
| PUT | `/api/workouts/{workoutId}/exercises/{index}` | `{exerciseId}` : ajoute un exercice après le dernier (idempotent, 80 au plus, séance en cours seulement) → `204` | connecté |
| PUT · DELETE | `/api/workouts/{workoutId}/sets/{id}` | `{exerciseIndex, kind, loadKg?, reps?, seconds?, rir?, doneAt}` : enregistre ou corrige une série (id de l'appareil ; `doneAt` entre 5 min avant le début et la fin ; une correction ne change pas d'exercice) ; la retire → `204` | connecté |
| POST | `/api/workouts/{id}/finish` | `{finishedAt}` : termine (idempotent) → `Workout` | connecté |
| DELETE | `/api/workouts/{id}` | Abandonne une séance lancée par erreur (idempotent : déjà abandonnée → `204` ; inconnue → 404) → `204` | connecté |
| GET | `/api/workouts/in-progress` | La séance en cours (0 ou 1), pour la reprendre ailleurs | connecté |
| GET | `/api/workouts?limit=&before=` | Séances terminées, la plus récente d'abord (résumé : exercices faits, séries de travail, volume) ; `before` (date ISO 8601) invalide → 400 | connecté |
| GET | `/api/workouts/{id}` | Une séance, chaque exercice avec dernière fois, suggestion, records d'avant et records battus | connecté |
| GET | `/api/recipes` | Recettes de l'utilisateur, avec composition (total, par portion, pour 100 g) | connecté |
| GET | `/api/recipes/{id}` | Une recette, ingrédient par ingrédient (`available: false` si l'aliment a été supprimé depuis) | connecté |
| POST | `/api/recipes` | `{name, servings, cookedWeightGrams?, ingredients: [{foodId, grams}]}` → `201 Recipe` | connecté |
| PUT | `/api/recipes/{id}` | Remplace une recette ; les entrées déjà saisies gardent leurs valeurs → `Recipe` | connecté |
| DELETE | `/api/recipes/{id}` | Supprime (soft delete) ; les repas favoris qui l'utilisent restent utilisables → `204` | connecté |
| GET | `/api/nutrition/favorite-meals` | Repas favoris, avec leurs éléments et leurs valeurs actuelles | connecté |
| POST | `/api/nutrition/favorite-meals` | `{name, day, slot}` : garde ce qui a été saisi dans ce repas → `201 FavoriteMeal` (422 si le repas est vide) | connecté |
| POST | `/api/nutrition/favorite-meals/{id}/log` | `{day, slot}` : saisit tous ses éléments d'un coup → `201 NutritionDay` | connecté |
| DELETE | `/api/nutrition/favorite-meals/{id}` | → `204` (le journal n'est pas touché) | connecté |
| GET / PUT | `/api/nutrition/meal-split` | Répartition des calories par repas (entiers, total 100) | connecté |
| GET | `/api/health` | État de l'API et de ses dépendances : `200` + `status: ok`, ou `503` + `status: degraded` et le détail `checks` | publique |

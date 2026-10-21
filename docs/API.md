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
| Limitation | 429 + `Retry-After` : connexion (5 / 15 min par IP+email, 30 / 15 min par IP, 50 / h par compte), inscription (5 / h par IP), liens email (10 / h par IP et par adresse), second facteur (5 / 15 min par compte), ressaisie du mot de passe (10 / 15 min par compte), écritures Training (600 / min par compte), écritures Progression (120 / min par compte), écritures Coach (analyses relancées, décisions, réglages : 30 / min par compte), écritures Notification (vérifications, lectures, réglages : 60 / min par compte), préférences (fuseau, tableau de bord : 30 / min par compte). Valeurs par variables `RATE_LIMIT_*`. |
| Jours | Un jour calendaire est `YYYY-MM-DD` (jour **local** de l'utilisateur, envoyé par la PWA) ; accepté jusqu'à demain (fuseaux en avance) et sur 10 ans d'historique. |
| Concurrence | 409 `shared.concurrent_update` quand deux requêtes modifient la même chose en même temps. |

## Endpoints

| Méthode | Route | Description | Auth |
| --- | --- | --- | --- |
| POST | `/api/auth/register` | Crée le compte (si `REGISTRATION_ENABLED`), envoie le lien de vérification, connecte l'appareil → `201 Account` | publique |
| POST | `/api/auth/sign-in` | Email + mot de passe → `{twoFactorRequired: false, account, deletionCancelled}` ou `{twoFactorRequired: true, account: null}` | publique |
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
| POST | `/api/me/deletion` | `{password, code?}` (le code de l'application ou de secours quand la 2FA est active ; mot de passe vide pour un compte Google sans mot de passe, qui doit s'être connecté sur cet appareil dans les 10 minutes, sinon 422 `identity.reauthentication_required`) → `202 {deletionDueAt}` : compte fermé tout de suite (toutes les sessions révoquées, cet appareil déconnecté), effacé définitivement 7 jours plus tard avec ses données et fichiers ; se reconnecter avant annule (`deletionCancelled: true` une fois dans la réponse de connexion, `?suppression_annulee=1` au retour de Google) ; 422 `identity.wrong_current_password` / `identity.invalid_second_factor_code` ; un email à chaque étape | connecté |
| PUT | `/api/me/time-zone` | `{timeZone}` IANA des appareils (envoyé par la PWA quand il change) → `204` ; 422 `identity.invalid_time_zone` ; gardé dans les préférences, à part du compte (n'entre jamais en conflit avec une écriture du compte) ; relu dans `Account.timeZone` | connecté |
| GET · PUT | `/api/me/dashboard` | `{widgets: [{key, visible}]}` : l'ordre et l'affichage des widgets du tableau de bord (`weight`, `nutrition`, `meals`, `workout`, `coach`, `goal`, `records`, `report`), chacun une fois (422 `identity.invalid_dashboard_layout`) ; l'ordre par défaut tant qu'il n'est pas réglé ; gardé à part du compte | connecté |
| GET | `/api/me/data-policy` | Les délais du serveur : `{deletionDelayDays, exportAvailableHours, activityRetentionDays, reauthenticationMinutes}` | connecté |
| GET | `/api/me/activity?limit=&before=&beforeId=` | Activité du compte : `{id, action (liste fermée, dans l'OpenAPI), details, source (user, coach, system), occurredAt}` ; une confirmation de suppression refusée y paraît (`identity.reauthentication_failed`), la plus récente d'abord (50 au plus par page, curseur date + id) ; gardée `AUDIT_RETENTION_DAYS` jours | connecté |
| GET | `/api/exports/domains` | Ce qu'un export peut couvrir : `[{key, title, periodFiltered}]`, dans l'ordre (`periodFiltered: false` : toujours complet, comme le compte) | connecté |
| POST | `/api/exports` | `{from?, to?, domains?}` → `202 Export` : construit par le worker (JSON, CSV, Excel, PDF, photos dans un ZIP) ; une seule en cours (`409 export.in_progress`), 5 par jour (429 ; une demande refusée ne compte pas) ; période prise dans le fuseau de l'utilisateur ; domaine inconnu ou période à l'envers → `422 export.invalid_scope` | connecté |
| GET | `/api/exports` · `/api/exports/{id}` | Les 10 derniers exports / un export : `{id, status (queued, processing, ready, failed, expired ; un export ouvert depuis 30 minutes passe failed), from, to, domains, requestedAt, readyAt, expiresAt, downloadable}` | connecté |
| POST | `/api/exports/{id}/download` | `{url}` : un lien signé de 5 minutes vers l'archive (gardée 24 h) ; inscrit le téléchargement à l'activité ; `409 export.not_ready` sinon | connecté |
| POST | `/api/me/email-verification` | Renvoie un lien (l'ancien meurt) → `204` | connecté |
| POST | `/api/me/password` | `{currentPassword, newPassword}`, déconnecte les autres appareils → `204` | connecté |
| POST | `/api/me/two-factor/setup` | `{password}` → `{secret, otpauthUri}` (email vérifié requis) | connecté |
| POST | `/api/me/two-factor/confirm` | `{code}` : active la 2FA → `{recoveryCodes}` (affichés une seule fois) | connecté |
| POST | `/api/me/two-factor/recovery-codes` | `{password}` → nouveaux `{recoveryCodes}` | connecté |
| POST | `/api/me/two-factor/disable` | `{password}` → `204` | connecté |
| GET | `/api/me/sessions` | Appareils connectés (`current` = celui-ci) | connecté |
| DELETE | `/api/me/sessions/{id}` | Déconnecte un appareil (404 si inconnu ou à quelqu'un d'autre) → `204` | connecté |
| GET | `/api/foods?q=&limit=` | Recherche (mots dans n'importe quel ordre, sans accents ni casse, 2 caractères min., 30 résultats max.) : aliments de l'utilisateur, puis noms qui commencent par la requête, puis les plus proches | connecté |
| GET | `/api/foods/recent` | 20 derniers aliments saisis (encore visibles), le plus récent d'abord, chacun avec la quantité de sa dernière saisie (`lastGrams`, `null` dans les autres listes d'aliments) | connecté |
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
| GET | `/api/nutrition/history?period=&today=` | Calories et protéines des jours **renseignés** et complets de la période (aujourd'hui exclu), la moyenne de chaque semaine et de la période, l'objectif en vigueur | connecté |
| GET | `/api/training/next-workout` | Le prochain jour du programme actif (celui qui suit la dernière séance terminée, en boucle ; le premier après une révision qui a moins de jours), son nombre d'exercices, quand ce jour de la version actuelle a été commencé pour la dernière fois (`lastDoneAt`), et si une séance est en cours ; 404 `training.no_active_program` | connecté |
| GET | `/api/training/analytics?period=&today=` | Toutes les séances terminées de la période (sans plafond) : séances, fréquence par semaine, séries de travail, reps, tonnage, records battus, au total et par semaine (semaines vides comprises) ; `musclesThisWeek` : les séries de travail de la semaine en cours (quelle que soit la période) par muscle principal de l'exercice, le plus travaillé d'abord | connecté |
| GET | `/api/progression/weight?period=&today=` | Dernière pesée, moyennes 7 / 30 jours et vitesse sur 4 semaines se terminant `today`, écart depuis la première pesée, écart sur la période (`periodChangeKg` : dernière moins première pesée de la période), objectif suivi, points de la période (pesée et moyennes du jour), moyenne de chaque semaine | connecté |
| GET | `/api/progression/weigh-ins?limit=&before=` | Pesées, la plus récente d'abord (100 au plus par page) ; `before` invalide → 400 | connecté |
| GET · PUT · DELETE | `/api/progression/weigh-ins/{day}` | `{weightKg}` (20–400, au dixième) : une pesée par jour, la renvoyer la remplace ; retirer deux fois ne change rien | connecté |
| GET · PUT · DELETE | `/api/progression/weight-goal?today=` | Objectif suivi et progression (jalons atteints, part du chemin, estimations) ; `{targetKg, milestonesKg}` le remplace, à partir de la dernière pesée (422 sans pesée) ; supprimer n'en suit plus | connecté |
| GET · POST | `/api/progression/measures` | Parties du corps puis mesures perso ; `{name}` en ajoute une (20 au plus, noms distincts) | connecté |
| PUT · DELETE | `/api/progression/measures/{key}` | Renomme ; archive (ses valeurs restent) | connecté |
| GET | `/api/progression/measurements?period=&today=` | Chaque mesure : dernière valeur, écart sur la période, points | connecté |
| GET · PUT · DELETE | `/api/progression/measurements/{day}` | `{centimetres: {clé: cm}}` : les mensurations d'un jour, remplacées en entier ; clé inconnue → 404 `progression.measure_not_found` | connecté |
| POST | `/api/progression/photos` | multipart `file` (JPEG, PNG, WebP, 15 Mo), `day`, `pose` (`front`, `side`, `back`, `other`) : réencodée en WebP sans métadonnées → `201 ProgressPhoto` (liens signés 5 min) | connecté |
| GET · DELETE | `/api/progression/photos/{id}` | Une photo avec des liens neufs ; la supprimer efface son fichier | connecté |
| GET | `/api/progression/photo-sessions` | Photos groupées par jour, avec le poids et les mensurations du jour (ou les plus récents d'avant) | connecté |
| GET | `/api/coach?today=` | Le Coach tel que la dernière analyse l'a laissé : 3 recommandations au plus (les plus importantes d'abord, celles mises de côté exclues ; une proposition dit aussi à peu près combien de glucides elle change), adhérence des 7 jours d'avant l'analyse expliquée par parts (`null` avant la première, score `null` si rien n'a été saisi), dernier rapport hebdomadaire, personnalité, dernier jour analysé | connecté |
| POST | `/api/coach/analysis` | `{today, again}` : lance l'analyse si elle n'a pas tourné ce jour ni un jour suivant (ou à nouveau avec `again`) puis répond le Coach ; tire le rapport de la semaine finie. Chaque analyse qui tourne est limitée ; deux en même temps : 409 | connecté |
| GET | `/api/coach/recommendations?limit=&before=&beforeId=` | Historique (50 au plus par page), la plus récente d'abord puis par id, avec les chiffres qui ont conduit à chacune et sa décision ; la page suivante part du `createdAt` **et** de l'`id` de la dernière reçue (celles d'une analyse ont la même heure) | connecté |
| GET | `/api/coach/recommendations/{id}` | Une recommandation | connecté |
| POST | `/api/coach/recommendations/{id}/decision` | `{decision: accept, reject ou postpone, today}` ; accepter une proposition calorique fixe un objectif à partir du lendemain de l'utilisateur (source `coach`, protéines et lipides gardés) ; 409 `coach.recommendation_closed` si elle n'est plus proposée, `coach.target_changed` si l'objectif a changé depuis ; 422 `coach.target_unreachable` si l'objectif serait invalide | connecté |
| GET | `/api/coach/reports?limit=&before=` | Les rapports hebdomadaires, le plus récent d'abord, 12 par page (52 au plus) ; `before` = le lundi du dernier reçu ; mal formé → 400 | connecté |
| GET · PUT | `/api/coach/settings` | `{personality: direct (par défaut), encouraging ou neutral, paceKgPerWeek}` (±1 kg/semaine, `null` = celui de l'objectif nutrition) | connecté |
| POST | `/api/notifications/check` | Lance les vérifications de l'utilisateur maintenant (à l'ouverture de l'app) → toujours `204` (hors 429) ; rien si les dernières ont moins de 5 minutes, si le programme le vérifie au même moment, pendant les horaires silencieux ou toutes catégories désactivées | connecté |
| GET | `/api/notifications?limit=&before=&beforeId=` | Notifications, les plus récentes d'abord (50 au plus par page, curseur `createdAt` + `id`) : catégorie, titre, texte, lien PWA, lue ou non | connecté |
| GET | `/api/notifications/unread` | `{count}` : non lues (le badge de la cloche) | connecté |
| GET | `/api/notifications/{id}` | Une notification | connecté |
| POST | `/api/notifications/{id}/read` · `/api/notifications/read-all` | Marque lue (la première fois est gardée) · toutes → `204` | connecté |
| GET · PUT | `/api/notifications/settings` | `{categories: {low_calories, workout_not_started, missing_weigh_in, goal_reached, record, weekly_report: bool}, quietFrom, quietTo}` (`HH:MM`, heure de l'utilisateur, deux heures différentes ; les deux `null` = pas d'horaires silencieux) ; chaque catégorie une fois, une seule des deux heures ou deux heures égales → 422 `notification.invalid_settings` | connecté |
| GET | `/api/media/{clé}?expires=&signature=&download=` | Stockage local uniquement (dev, tests) : sert un fichier par lien signé, `Cache-Control: private, no-store` ; absent en production (S3 présigné) | connecté + lien signé |
| GET | `/api/health?scope=` | État de l'API et de ses dépendances : `200` + `status: ok`, ou `503` + `status: degraded` et le détail `checks` (sonde du conteneur). Avec `scope=monitoring`, aussi `monitors` (`null` sinon) : `worker` (planificateur, toutes les 15 min), `backup` (chaque nuit), `backup_verify` (chaque semaine), `disk` — `ok`, `late`, `unknown`, `low`, `pending` (pas encore dû) ou `disabled` ; à réserver avant toute exposition publique (docs/MONITORING.md) | publique |
| GET | `/api/client-config` | Réglages du déploiement que la PWA lit au démarrage : `environment`, `release`, `errorReportingDsn` (signalement des erreurs, `null` = aucun) ; rien de secret, en cache public 5 min | publique |

# Domaine

Langage commun et règles structurantes. Chaque contexte complètera sa section lors de sa phase.

## Principes transverses

- **L'utilisateur garde le contrôle** : aucune modification significative (calories, macros, objectifs,
  programme, stratégie) n'est appliquée sans validation `[Accepter] [Refuser] [Plus tard]`.
- **Symfony calcule, l'IA formule** : moyennes, tendances, écarts, ajustements sont calculés de façon
  déterministe et testée ; un provider IA ne fait que rédiger à partir d'une analyse structurée.
- **Pas d'histoire réécrite** : une séance reste liée à la version du programme sous laquelle elle a été faite ;
  tout changement de valeur importante est audité (qui, quand, pourquoi, source).
- **Tout est personnel** : chaque donnée métier appartient à un utilisateur (`UserId`) et chaque lecture est filtrée par lui.
- **Soft delete** (`deleted_at`) pour les données métier importantes ; suppression définitive lors d'une suppression de compte.

## Noyau partagé (`Shared`)

| Concept | Rôle |
| --- | --- |
| `Uuid` | Value object de base de tous les identifiants (UUID RFC 9562, forme canonique minuscule). |
| `UuidGenerator` | Port de génération ; implémentation UUID v7 en infrastructure. |
| `AggregateRoot` | Enregistre les événements de domaine ; le handler les publie (`publish(...$aggregate->pullDomainEvents())`), livrés après le commit (ADR-0014). |
| `DomainEvent` | Fait passé (`WorkoutCompleted`, `WeightMeasurementRecorded`…), moyen privilégié de communication entre contextes. |
| `DomainException` | Violation d'une règle métier, avec un **code d'erreur stable** (`nutrition.invalid_recipe`) traduit côté présentation. |

## Contextes

| Contexte | Phase | Responsabilité |
| --- | --- | --- |
| Identity | 1 | Compte, profil, préférences, sessions, appareils, 2FA, OAuth |
| Nutrition | 2–3 | Aliments (cru/cuit), produits scannés, recettes et portions, repas, favoris, journal, objectifs |
| Training | 4 | Exercices, programmes versionnés, séances, séries (RIR, tempo, supersets, drop sets), progression, PR |
| Progression | 5 | Pesées et moyennes, objectifs et jalons, mensurations, photos, analytics |
| Coach | 6 | Moteur de règles, analyses, recommandations expliquées (max. 3), historique des décisions |
| Notification | 7 | Catégories activables, horaires silencieux, déduplication contextuelle |
| Media | 5/8 | Stockage privé des fichiers, URLs signées courte durée |
| Export | 8 | Exports CSV/Excel/PDF/JSON/photos, suppression de compte |
| System | 0 | Opérationnel, non métier : santé du service (ADR-0013) |

## Identity (phase 1)

| Concept | Règle |
| --- | --- |
| `User` (agrégat) | Email unique (normalisé en minuscules), mot de passe haché, email vérifié ou non, `Profile`. |
| `Profile` | Prénom (1–50), date de naissance (13–120 ans), sexe (femme/homme, pour les formules énergétiques), taille (100–250 cm). Le poids appartient à Progression. |
| `PasswordPolicy` | 12 à 4096 caractères, sans règle de composition (NIST 800-63B). |
| `UserSession` | Un appareil connecté : libellé grossier (« Firefox · Android »), activité enregistrée au plus toutes les 5 min, révocable. Révoquer = l'appareil est refusé à sa requête suivante. |
| `OneTimeToken` | Lien email à usage unique `<id>.<secret>` ; seul le SHA-256 du secret (256 bits) est stocké. Vérification : 7 jours, réinitialisation : 1 h. Un nouveau lien invalide les précédents. Mauvais secret, expiré, déjà utilisé : même erreur. |

| Double authentification | TOTP (RFC 6238, 6 chiffres, 30 s, ±1 pas) : graine chiffrée au repos, active seulement après un premier code valide ; un pas de temps n'est jamais accepté deux fois. 10 codes de secours à usage unique. Exige un email vérifié et le mot de passe pour activer, désactiver ou régénérer les codes. |
| `ExternalIdentity` | Lien « compte Google (sujet stable) → utilisateur ». Jamais l'email comme clé. |

Règles transverses :

- **Inscription** ouverte ou fermée par `REGISTRATION_ENABLED` ; l'inscription connecte immédiatement l'appareil.
- **Email non vérifié** : le compte fonctionne, mais la réinitialisation du mot de passe (et la 2FA) exigent un email vérifié.
- **Réinitialisation** : déconnecte tous les appareils. **Changement** (mot de passe actuel requis) : déconnecte les autres.
- **Non-divulgation** : email inconnu et mauvais mot de passe répondent pareil, y compris en temps de réponse ; une demande de réinitialisation répond toujours 202.
- **2FA active** : le bon mot de passe (ou un fournisseur externe) ne suffit pas ; la connexion attend 5 min le second facteur, côté serveur.
- **Fournisseur externe** : identité déjà liée → ce compte ; même email → liaison seulement si notre compte **et** le fournisseur ont vérifié l'adresse
  (sinon refus : protection contre la pré-création d'un compte au nom d'autrui) ; sinon nouveau compte vérifié, sans mot de passe (à définir via « mot de passe oublié »).
- Le **journal de sécurité** (canal `audit`) enregistre inscriptions, vérifications, changements de mot de passe, activation/désactivation de la 2FA et usage des codes de secours, sans donnée sensible.

`UserId` vit dans le noyau partagé (ADR-0015) : chaque contexte référence le propriétaire sans dépendre d'Identity.

## Nutrition (phase 2)

| Concept | Règle |
| --- | --- |
| `Food` (agrégat) | Composition **pour 100 g** (`NutritionFacts`), état cru / cuit / sans objet, portions nommées (≤ 10). Source `ciqual` (partagée, lecture seule, rafraîchie à chaque import) ou `custom` (à un utilisateur, modifiable, soft delete). |
| `NutritionFacts` | Énergie 0–950 kcal (l'huile fait 900–901 dans les tables), macros 0–100 g, somme ≤ 105 g (arrondis), sucres ≤ glucides, saturés ≤ lipides. |
| `FoodState` | Déduit du nom CIQUAL (« cuit », « sauté/poêlé », « œuf dur »… avant « cru » ; « blé dur » n'est pas une cuisson) ; un aliment cru et son équivalent cuit sont deux aliments distincts. |
| `JournalEntry` (agrégat) | Une quantité (0–5000 g) d'un aliment, dans un repas d'un jour. **Instantané** : nom, état et valeurs pour 100 g sont copiés à la saisie ; modifier ou supprimer l'aliment (ou un nouvel import CIQUAL) ne réécrit jamais le passé. |
| `MealSlot` | Petit-déjeuner, déjeuner, collation, gainer, dîner (ordre chronologique). |
| `NutritionTarget` (agrégat) | Objectifs quotidiens **à partir d'un jour** (aujourd'hui ou demain), jamais modifiés : un changement = un nouvel objectif. Chaque jour est comparé à l'objectif en vigueur ce jour-là : le dernier fixé parmi ceux déjà effectifs. Source : estimation, saisie manuelle, coach (phase 6). |
| `MacroTargets` | 1000–7000 kcal ; les macros (4 / 4 / 9 kcal/g) doivent faire les calories à ±5 %. |
| `EnergyCalculator` | Mifflin-St Jeor : `10·kg + 6,25·cm − 5·âge + 5 (homme) / −161 (femme)`, × (mode de vie 1,2 / 1,35 / 1,5 / 1,7 + 0,05 par séance hebdo, max. +0,3), + variation visée × 7700 ÷ 7, arrondi à 10 kcal, entre 1200 et 7000. Lipides 25 % des calories, au moins 0,6 g/kg (au plus 50 % des calories) ; protéines 1,8 (masse) / 1,6 (maintien) / 2,2 (sèche) g/kg, plafonnées à ce que les calories permettent (grande sèche d'une personne lourde) ; glucides : le reste. |
| `MealSplit` | Part des calories par repas, entiers dont la somme fait 100 ; défaut 25 / 30 / 0 / 15 / 30. |
| `Recipe` (agrégat) | Plat de 1 à 50 ingrédients pesés (aliments CIQUAL ou personnels), 1 à 50 portions, poids cuit facultatif. Sa composition (`RecipeComposition`) suit les valeurs **actuelles** de ses aliments : total, par portion, pour 100 g — du plat cuit s'il a été pesé, sinon du poids cru. Se saisit en grammes du plat (une portion = poids ÷ portions). Soft delete. |
| `MealBuilder` | Service de domaine (ADR-0021) : à partir d'un `MacroGoal` (ce qui reste, jamais négatif) et d'aliments candidats, propose jusqu'à 3 repas : aliments classés par rôle (protéine ≥ 35 % des kcal, lipide ≥ 70 %, glucide ≥ 55 %, sinon accompagnement ; < 20 kcal/100 g ignorés), quantités ajustées par moindres carrés bornés sur P / G / L en kcal, arrondies (1 g pour les huiles, 5 g sinon), jamais plus de 110 % des calories restantes, chacun autour d'un aliment principal différent. Rien sous 100 kcal restantes. |
| `FavoriteMeal` (agrégat) | Repas habituel (1 à 30 éléments : aliment **ou** recette, avec sa quantité), créé à partir d'un repas déjà saisi, rajouté en un geste dans n'importe quel repas (une entrée par élément, chacune figée à ce moment-là). |

Règles transverses :

- Une estimation n'est qu'un **aperçu** : rien n'est enregistré avant que l'utilisateur valide. Enregistrée telle quelle → source `estimate` ;
  ajustée → `manual`, l'estimation restant attachée pour expliquer le point de départ. Chaque changement est tracé (canal `audit`, `nutrition.target_set` : ancien → nouveau, source).
- Un nouvel objectif s'applique **à partir d'aujourd'hui** (ou demain) : il ne réécrit pas les jours passés.
- Les **moyennes** 7 / 30 jours portent sur les jours **complets** renseignés (aujourd'hui exclu, il est en cours) : un jour sans saisie est « non renseigné », pas « zéro mangé ».
- Les fibres d'un total restent **inconnues** tant qu'aucun aliment ne les donne (jamais « 0 g » par défaut).
- Un repas du Meal Builder ne reçoit jamais plus que ce qui reste de la **journée**, même si sa part de la répartition est plus grande.
- Le dépassement d'un objectif n'est pas une erreur : la PWA l'affiche de façon neutre (« +320 kcal »).
- Nutrition ne lit pas Identity : sexe, date de naissance et taille sont **envoyés** avec la demande d'estimation (préremplis par la PWA depuis le profil),
  et le poids est saisi jusqu'à ce que Progression le fournisse (phase 5).
- Un aliment personnel ou une recette supprimés disparaissent de la recherche et des listes, mais continuent de **composer** les recettes et
  repas favoris qui les utilisent (soft delete) ; la fiche de la recette le signale.
- **Base CIQUAL** (Anses, Licence Ouverte Etalab 2.0, ADR-0019) : importée par `nutrition:catalogue:import`, idempotente.

Limites connues (assumées pour une application personnelle à un utilisateur, à revoir en phase 8) :

- Le verrou optimiste (`version`) protège des écritures simultanées, pas de deux appareils qui modifient chacun une copie ancienne
  d'un même aliment, recette ou répartition : la dernière enregistrée l'emporte.
- Pas de quota ni de limitation de débit sur les écritures Nutrition ni sur le Meal Builder (compte authentifié ; coût du Meal Builder borné
  par 6 candidats par rôle, quel que soit le nombre d'aliments).

## Événements de domaine prévus

`WeightMeasurementRecorded` · `DailyNutritionTargetReached` · `WorkoutCompleted` · `GoalChanged` ·
`RecommendationCreated` · `RecommendationAccepted` · `RecommendationRejected`

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
| `DomainEvent` | Fait passé (`WorkoutCompleted`, `WeighInRecorded`…), moyen privilégié de communication entre contextes. |
| `DomainException` | Violation d'une règle métier, avec un **code d'erreur stable** (`nutrition.invalid_recipe`) traduit côté présentation. |
| `Period`, `Days` | Les périodes des graphiques (7 j, 30 j, 3 mois, 6 mois, 1 an, tout) se terminant le jour de l'utilisateur, et le calcul des jours et semaines (lundi). |
| `MediaId`, port `Photos` | Une photo que Media garde pour un autre contexte (ADR-0027) : `store`, `links`, `delete`, Media vérifiant le propriétaire. |

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
| `Food` (agrégat) | Composition **pour 100 g** (`NutritionFacts`), état cru / cuit / sans objet, portions nommées (≤ 10), code-barres éventuel. Source `ciqual` (partagée, lecture seule, rafraîchie à chaque import), `off` (produit Open Food Facts confirmé par un utilisateur : partagé, lecture seule, clé = code-barres) ou `custom` (à un utilisateur, modifiable, soft delete). |
| `Barcode` | 8, 12, 13 ou 14 chiffres, clé de contrôle GS1 vérifiée ; normalisé (UPC-A et GTIN-14 commençant par 0 → EAN-13). Un code désigne au plus un des aliments personnels d'un utilisateur. |
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
- **Produits scannés** (ADR-0022) : un scan ne sauve rien ; l'utilisateur confirme (produit partagé, valeurs relues à la source) ou corrige
  (son aliment personnel, avec le code-barres). Un scan trouve d'abord l'aliment de l'utilisateur, puis le produit partagé.

Limites connues (assumées pour une application personnelle à un utilisateur, à revoir en phase 8) :

- Le verrou optimiste (`version`) protège des écritures simultanées, pas de deux appareils qui modifient chacun une copie ancienne
  d'un même aliment, recette ou répartition : la dernière enregistrée l'emporte.
- Pas de quota ni de limitation de débit sur les écritures Nutrition ni sur le Meal Builder (compte authentifié ; coût du Meal Builder borné
  par 6 candidats par rôle, quel que soit le nombre d'aliments).
- Scan (phase 3), reporté volontairement : « ajouter et scanner le suivant » (enchaîner plusieurs produits sans refermer le tiroir),
  rattacher un code inconnu à un aliment personnel déjà saisi (il faut aujourd'hui modifier cet aliment pour y mettre le code), et
  l'E2E du lecteur WebAssembly avec une fausse caméra (couvert par Vitest ; l'E2E saisit le code).

## Training (phase 4)

| Élément | Règles |
| --- | --- |
| `Exercise` (agrégat) | Bibliothèque (`library`, clé stable, partagée, lecture seule, rafraîchie à chaque démarrage) ou perso (`custom`, à un utilisateur, modifiable, suppression douce). Muscle principal + jusqu'à 6 secondaires, type (poly/isolation), équipement, **mesure** (charge × reps, poids du corps × reps, durée), description, lien https, valeurs habituelles (fourchette, RIR, repos, tempo). |
| `ExerciseNote` (agrégat) | Consigne permanente d'un utilisateur pour un exercice (≤ 500 caractères), bibliothèque comprise ; une par exercice. |
| `Program` (agrégat) | Nom + version courante ; un seul actif par utilisateur (un programme créé quand aucun n'est suivi le devient), archivage doux. Au plus 50 par utilisateur et 500 versions chacun. |
| `ProgramVersion` (agrégat immuable) | 1 à 7 jours aux noms distincts → 1 à 15 blocs → 1 à 4 exercices (plusieurs = superset). Changer les jours crée la version suivante ; renommer non. |
| `PlannedExercise` (VO) | 1 à 10 séries de travail, fourchette 1–300 (reps ou secondes), RIR 0–10 facultatif, repos 0–900 s, tempo 4 phases (`3-1-1-0`, `X` = explosif), stratégie de progression (double, linéaire, charge fixe, reps fixes, RIR cible), palier 0,25–20 kg, 0 à 5 séries d'échauffement, drop set après la dernière, consigne propre au programme. |
| `Workout` (agrégat) | Séance : jour de programme suivi (version figée) ou séance libre, copie des exercices prévus (+ ajoutés), séries faites. Id et horaires de l'appareil (début sur les 30 derniers jours, séries entre 5 min avant le début et la fin, un jour au plus), 80 exercices au plus, écritures idempotentes, une seule en cours par utilisateur, terminée seulement sur demande, séries corrigeables ensuite (sans changer d'exercice), abandon = suppression douce, idempotent. Garde le nom du programme tel qu'il était. |
| `WorkoutSet` (VO) | Échauffement, approche, normale, effective, dégressive (drop) ; charge 0–1 000 kg et 0–300 reps, ou lest facultatif + reps (poids du corps), ou 1–3 600 s ; RIR facultatif. Seules les séries normales et effectives comptent (progression, records). |
| `ProgressionAdvisor` (service) | Suggestion depuis la dernière séance où l'exercice a été fait, sur ses séries de travail les plus lourdes (autant que de séries prévues) : double progression (toutes les séries au haut de la fourchette → +palier, sinon +1 rep), linéaire (toutes au bas → +palier), charge fixe (+1 rep), reps fixes (+palier si RIR > cible), RIR cible (écart ≥ 2 → ±palier) ; durées : +5 s. Jamais appliquée seule. |
| `RecordBook` (service) | Records par exercice : charge max, 1RM estimé (Epley, ≤ 12 reps), volume de la séance, reps (poids du corps), durée, reps à une charge déjà faite. Un record bat une séance antérieure : la première fois n'en est pas un. Calculé sur les 300 dernières séances où l'exercice figure. |

Règles transverses :

- Au plus 300 exercices perso par utilisateur ; écritures Training limitées à 600 / min par compte (`RATE_LIMIT_TRAINING_WRITES`).
- Le déroulé d'une séance (repos à lancer, supersets, drop sets, valeurs de départ) est décidé par la PWA pour marcher hors ligne
  (ADR-0024) ; la progression et les records restent calculés par l'API.

Limites connues (reportées volontairement) :

- Pas de préréglages de repos.
- La clé de recherche (`SearchKey`) est dupliquée entre Nutrition et Training plutôt que partagée (contextes isolés).
- La prescription habituelle d'un exercice ajouté en séance est une règle de l'appareil, pas encore exposée par l'API.
- Records recalculés à la lecture (pas de table de projection) : borné par les 300 dernières séances.
- Séance en cours sur un autre appareil : proposée « Reprendre / Terminer », sans écran dédié pour résoudre un conflit (409).

Analyses (phase 5) : `GET /api/training/analytics` — séances terminées de la période, fréquence par semaine, séries de
travail, reps, tonnage, records battus (chacun jugé contre les séances d'avant, sur les 300 dernières), par semaine.

## Progression (phase 5)

| Élément | Règles |
| --- | --- |
| `WeighIn` (agrégat) | Le poids d'un jour de l'utilisateur (20–400 kg, au dixième) : une pesée par jour, la renvoyer la remplace ; un jour déjà venu (jusqu'à demain pour un fuseau en avance), depuis 2000. Annonce `WeighInRecorded` (pour le Coach). |
| `WeightTrend` (service) | Moyennes 7 et 30 jours des pesées des jours se terminant aujourd'hui (pas de pesée = pas de moyenne) ; vitesse en kg/semaine par moindres carrés sur 4 semaines (4 pesées au moins, sur 7 jours ou plus) ; stable sous 50 g/semaine ; points du graphique avec les moyennes du jour. |
| `WeightGoal` (agrégat) | Une cible et jusqu'à 10 jalons entre le poids de départ (la dernière pesée) et la cible, triés dans le sens du poids ; au moins 0,5 kg d'écart. Un seul suivi : en fixer un autre termine le précédent (gardé). Indépendant du but Nutrition. |
| `GoalProgress`, `Estimate` | Chaque étape atteinte le jour de la première pesée qui la franchit depuis le départ ; part du chemin (0–100 %) ; écart depuis la dernière étape atteinte (« depuis l'objectif précédent ») ; semaines restantes au rythme actuel, arrondies au-dessus, **présentées comme une estimation**, sinon pourquoi pas (pas assez de pesées, poids stable, dans l'autre sens). |
| `CustomMeasure` (agrégat) | Une mesure perso en cm (1–40 caractères) : 20 actives au plus et 60 créées en tout (archivées comprises) ; noms distincts sans la casse, **archivées comprises** (on la rétablit plutôt que d'en créer une homonyme) ; archivée plutôt que supprimée : ses valeurs restent. |
| `MeasurementDay` (agrégat) | Les mensurations d'un jour : une valeur (1–300 cm, au millimètre) par mesure prise, parmi les 10 parties du corps et les mesures perso non archivées ; une fois par jour, remplacées en entier ; les valeurs d'une mesure archivée depuis sont gardées au remplacement. |
| `ProgressPhoto` (agrégat) | Un jour, une pose (face, profil, dos, autre), la taille, et le `MediaId` du fichier que Media garde ; 500 au plus. Une **session** est l'ensemble des photos d'un jour, avec le poids et les mensurations du jour ou les plus récents d'avant (§61). |

Règles transverses :

- Les jours sont ceux de l'utilisateur (envoyés par l'appareil, `?today=` pour les lectures, à un jour près du serveur).
- Aucune de ces valeurs n'est modifiée par l'application : la PWA préremplit le poids de l'estimation Nutrition avec la dernière
  pesée, que l'utilisateur garde ou change.
- **Calories face au poids** (§56) : Nutrition donne ses moyennes par semaine (`GET /api/nutrition/history`), Progression les
  siennes ; la PWA les pose sur les mêmes semaines, en deux graphiques (ADR-0026). Aucun contexte ne lit l'autre.
- Écritures limitées à 120 / min par compte (`RATE_LIMIT_PROGRESSION_WRITES`).

Limites connues (reportées volontairement) :

- Adhérence (§58) et rapport hebdomadaire (§59) : phases 6–7, avec le Coach et le tableau de bord.
- Les pesées et mensurations ne se saisissent pas hors ligne (contrairement aux séances).
- Une photo ne se corrige pas (jour, pose) : la supprimer et la reprendre.
- Analyses d'entraînement (`GET /api/training/analytics`) : calculées sur les 300 dernières séances au plus
  (`WorkoutRepository::HISTORY_LIMIT`) ; au-delà, « Tout » et « 1 an » ne comptent que celles-là.
- Les séances sont rangées dans leur semaine d'après leur heure de début en UTC : une séance commencée juste après minuit
  heure locale, un lundi, peut tomber dans la semaine précédente (les pesées et les repas, eux, suivent le jour de
  l'utilisateur).
- La semaine en cours et la première semaine d'une période sont souvent incomplètes : la PWA marque la semaine en cours,
  l'écarte de la comparaison calories / poids et montre le nombre de jours saisis et de pesées de chaque semaine.

## Media (phase 5)

| Élément | Règles |
| --- | --- |
| `MediaFile` (agrégat) | Un fichier gardé pour son propriétaire : l'image réencodée (WebP, 2 048 px au plus) et sa miniature (480 px), sous des clés opaques (l'identifiant, ni le nom d'origine ni l'utilisateur). |
| `PhotoLibrary` | Implémente le port `Photos` : vérifie le type réel, la taille et les pixels avant de décoder, refuse les animations, réencode (GD) sans métadonnées, écrit, signe des liens de 5 minutes, supprime pour de bon. |

## Événements de domaine prévus

`WeighInRecorded` (émis en phase 5) · `DailyNutritionTargetReached` · `WorkoutCompleted` · `GoalChanged` ·
`RecommendationCreated` · `RecommendationAccepted` · `RecommendationRejected`

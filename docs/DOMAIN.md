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
| `DomainException` | Violation d'une règle métier, avec un **code d'erreur stable** (`nutrition.recipe.no_portion`) traduit côté présentation. |

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

## Décisions ouvertes pour la phase 1

- **`UserId` partagé** : chaque contexte doit référencer le propriétaire sans dépendre d'`Identity`. Proposition :
  `Shared\Domain\Identifier\UserId` + un port applicatif « utilisateur courant » (ADR à rédiger).
- **Types Doctrine des identifiants** : type abstrait `UuidType` (colonne `uuid` PostgreSQL) dans `Shared\Infrastructure`.
- **Erreurs métier HTTP** : conversion des `DomainException` en problem+json avec `code` stable.

## Événements de domaine prévus

`WeightMeasurementRecorded` · `DailyNutritionTargetReached` · `WorkoutCompleted` · `GoalChanged` ·
`RecommendationCreated` · `RecommendationAccepted` · `RecommendationRejected`

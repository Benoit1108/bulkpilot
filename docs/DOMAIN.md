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

## Événements de domaine prévus

`WeightMeasurementRecorded` · `DailyNutritionTargetReached` · `WorkoutCompleted` · `GoalChanged` ·
`RecommendationCreated` · `RecommendationAccepted` · `RecommendationRejected`

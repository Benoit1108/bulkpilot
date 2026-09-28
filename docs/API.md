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
| Validation | Contraintes sur les DTO d'entrée → `422` avec `violations[]`. |
| Pagination | Hydra (`hydra:view`) ; taille par défaut 30. |
| Identifiants | UUID dans les IRIs. |
| Authentification | Cookie de session httpOnly, `SameSite=Lax`, même origine (phase 1, ADR-0008) — les opérations ne sont donc pas `stateless`. |
| Traçabilité | Chaque réponse porte `X-Request-Id` (posé par Caddy), repris dans les erreurs côté PWA. |
| Erreurs métier | Les `DomainException` deviennent du problem+json avec leur `code` stable (`identity.invalid_token`…) ; statut selon la catégorie : `NotFound` 404, `Conflict` 409, `AuthenticationFailed` 401, autre règle 422. La PWA traduit le `code`. |
| CSRF | Requête mutante refusée (403) si `Origin` ≠ l'hôte ou `Sec-Fetch-Site` indique un autre site. |
| Limitation | 429 + `Retry-After` : connexion (5 / 15 min par IP+email, 30 / 15 min par IP, 50 / h par compte), inscription (5 / h par IP), liens email (10 / h par IP et par adresse), second facteur (5 / 15 min par compte), ressaisie du mot de passe (10 / 15 min par compte). Valeurs par variables `RATE_LIMIT_*`. |
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
| GET | `/api/health` | État de l'API et de ses dépendances : `200` + `status: ok`, ou `503` + `status: degraded` et le détail `checks` | publique |

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
| Erreurs métier | *Phase 1* : les `DomainException` seront converties en problem+json avec leur `code` stable (aujourd'hui : 500). |

## Endpoints

| Méthode | Route | Description | Auth |
| --- | --- | --- | --- |
| GET | `/api/health` | État de l'API et de ses dépendances : `200` + `status: ok`, ou `503` + `status: degraded` et le détail `checks` | publique |

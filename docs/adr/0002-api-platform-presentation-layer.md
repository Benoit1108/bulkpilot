# 0002 — API Platform comme simple couche d'exposition

**Contexte.** API Platform apporte OpenAPI, validation, sérialisation, pagination et filtres, mais pousse
naturellement vers un CRUD branché directement sur les entités Doctrine.

**Décision.** Les ressources API sont des classes dédiées de `Presentation/Api`, jamais les entités du domaine.
Les écritures passent par un processor qui dispatche une command ; les lectures par un provider qui pose une query.
Les routes sont dérivées des use cases (`POST /api/workouts/{id}/finish`), pas d'un CRUD générique.

**Conséquences.** Plus de code de mapping ; le modèle métier est libre d'évoluer sans casser le contrat HTTP,
et le contrat OpenAPI reste le reflet des intentions métier.

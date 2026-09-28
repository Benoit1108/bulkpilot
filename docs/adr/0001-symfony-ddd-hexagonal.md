# 0001 — Symfony en DDD / hexagonal par bounded context

**Contexte.** Le cœur métier (calculs nutritionnels, progression, règles du Coach) doit rester fiable et
testable, et évoluer d'un utilisateur vers un SaaS sans réécriture.

**Décision.** Le code est organisé par bounded context (`Identity`, `Nutrition`, `Training`, `Progression`,
`Coach`, `Notification`, `Media`, `Export`) plus un noyau `Shared` minimal. Chaque contexte a les couches
`Domain`, `Application`, `Infrastructure`, `Presentation`. Le domaine ne dépend que de PHP et des PSR.
Les contextes communiquent par commands, queries et événements de domaine ; ils ne dépendent que de `Shared`.
Deptrac vérifie ces deux règles en CI.

**Conséquences.** Un peu plus de fichiers par use case ; en échange, règles testables sans framework,
adapters remplaçables, frontières explicites. Toute dépendance directe entre deux contextes demande un nouvel ADR.

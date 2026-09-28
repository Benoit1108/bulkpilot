# 0010 — Identifiants UUID v7

**Contexte.** Identifiants non devinables, fusion future de données, index compacts.

**Décision.** UUID v7 (ordonnés dans le temps) générés côté serveur via le port `UuidGenerator`,
encapsulés dans des value objects typés (`UserId`, `WorkoutId`…) héritant de `Shared\Domain\Identifier\Uuid`.

**Point ouvert (phase 4).** La saisie hors ligne des séries impose que le client crée l'identifiant
avant synchronisation (idempotence des renvois). La règle sera : UUID générés par le serveur par défaut,
UUID fournis par le client acceptés uniquement pour les écritures synchronisables, validés et rattachés
à l'utilisateur authentifié. Cette exception fera l'objet d'un ADR dédié.

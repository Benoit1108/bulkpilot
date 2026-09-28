# 0015 — `UserId` dans le noyau partagé

**Contexte.** Chaque donnée personnelle (repas, séance, pesée…) appartient à un utilisateur, mais un contexte
ne peut pas dépendre d'Identity (ADR-0001).

**Décision.** `App\Shared\Domain\Identifier\UserId` et le port `App\Shared\Application\Security\CurrentUser`
font partie du noyau partagé. Les use cases reçoivent le `UserId` explicitement ; seule la couche Presentation
le résout via `CurrentUser` (implémenté par Identity sur Symfony Security). Le type Doctrine `user_id` est partagé.

**Conséquences.** Tous les contextes filtrent par propriétaire sans couplage à Identity ; le noyau partagé grandit
d'un concept, justifié parce qu'il est universel.

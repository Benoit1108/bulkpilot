# 0003 — FrankenPHP en worker mode

**Contexte.** Serveur HTTP moderne (HTTP/2-3, TLS automatique via Caddy) et latence faible pour la saisie en séance.

**Décision.** FrankenPHP est le serveur de référence, en worker mode dans tous les environnements
(avec `--watch` en dev) grâce au support natif de `symfony/runtime` 8. Caddy sert aussi la PWA.

**Conséquences.** Le kernel vit entre les requêtes : aucun état de requête ou d'utilisateur dans les services ;
les caches mémoire implémentent `ResetInterface` ; tests dédiés d'absence de fuite entre utilisateurs dès la phase 1.
`FRANKENPHP_RESET_KERNEL` reste disponible en filet de sécurité si un doute apparaît.

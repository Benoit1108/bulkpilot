# 0011 — Nom technique neutre

**Contexte.** « BulkPilot » est un nom de travail.

**Décision.** Namespace PHP `App\`, package front `app-pwa`, aucun nom commercial dans le domaine.
Le nom affiché provient de `APP_DISPLAY_NAME` (API) et `NUXT_PUBLIC_APP_NAME` (PWA). Seuls les noms d'images
Docker et du projet Compose portent le nom de travail.

**Conséquences.** Un changement de marque touche la configuration et les assets, pas le code métier.

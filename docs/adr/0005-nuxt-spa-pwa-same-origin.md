# 0005 — Nuxt en SPA PWA servie sur la même origine

**Contexte.** Application personnelle derrière authentification, utilisée surtout en PWA installée sur mobile,
y compris en salle avec un réseau instable. Aucun besoin de SEO.

**Décision.** Nuxt 4 avec `ssr: false`. En production, la SPA est compilée dans l'image FrankenPHP et servie par
Caddy sur la **même origine** que l'API (`/api/*` → Symfony, le reste → fichiers statiques avec fallback `index.html`).
En dev, Caddy proxifie le serveur Vite. Le service worker (Workbox) met en cache le shell, jamais les réponses API ;
depuis la refonte (ADR-0034), il garde aussi les illustrations d'exercices déjà vues (cache à part, un mois).
Les mises à jour s'appliquent sur confirmation de l'utilisateur.

**Conséquences.** Pas de serveur Node en production, pas de CORS, cookies first-party ; le premier rendu dépend
du JavaScript (acceptable ici). La résilience hors ligne des saisies critiques (séries) sera traitée en phase 4
avec une file locale synchronisée.

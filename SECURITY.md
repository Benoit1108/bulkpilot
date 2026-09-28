# Sécurité

## Signaler une vulnérabilité

Projet privé : contacter directement le mainteneur. Ne pas ouvrir d'issue publique.

## Mesures en place (phase 0)

- **Même origine** pour la PWA et l'API : pas de CORS, cookies first-party.
- En-têtes HTTP : `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`
  (caméra limitée à l'app pour le scan), `Cross-Origin-Opener-Policy`, HSTS en HTTPS, en-tête `Server` retiré.
- Logs d'accès Caddy **sans** `Cookie`, `Authorization` ni `Set-Cookie`.
- Réponses API jamais mises en cache par le service worker (route `NetworkOnly` sur le chemin `/api/*`).
- Chunks `/_nuxt` manquants : simple 404, jamais une page HTML mise en cache un an.
- Image de production **non-root**, sans Composer ni git ni fichiers de dev ; démarrage refusé sans `APP_SECRET`.
- Ports de dev (PostgreSQL, Mailpit) liés à `127.0.0.1`.
- CI : actions tierces épinglées par SHA, `persist-credentials: false`, permissions `contents: read`.
- Pas de stack trace en production (problem+json) ; Swagger UI désactivé en production.
- Secrets : aucun dans le dépôt (`.env.example` sans valeur sensible), scan `gitleaks` en CI,
  audit des dépendances Composer/pnpm en CI, Dependabot hebdomadaire.
- Aucun appel direct du frontend vers la base, l'IA, Open Food Facts ou le stockage privé : tout passe par Symfony.

## Prévu

| Sujet | Phase |
| --- | --- |
| Hash de mot de passe `auto` (Argon2id/bcrypt), sessions en base révocables, liste des appareils | 1 |
| 2FA (TOTP), rate limiting et anti brute-force sur l'authentification, journal de sécurité | 1 |
| CSRF : `SameSite=Lax` + vérification d'origine sur les requêtes mutantes | 1 |
| Contrôle d'accès par propriétaire sur chaque ressource, tests d'isolation entre utilisateurs | 1+ |
| Photos en bucket privé, URLs signées courte durée | 5/8 |
| Données minimales envoyées aux providers IA, provider journalisé | 6 |
| Audit des actions sensibles, export et suppression de compte | 8 |
| Content-Security-Policy (l'`index.html` généré contient un script inline : hashes ou nonces nécessaires) | 7 |
| Désactiver l'API admin Caddy (`localhost:2019`) ou la restreindre, en prévision des appels sortants (IA, Open Food Facts) | 3 |

# Sécurité

## Signaler une vulnérabilité

Projet privé : contacter directement le mainteneur. Ne pas ouvrir d'issue publique.

## Mesures en place

- **Même origine** pour la PWA et l'API : pas de CORS, cookies first-party.
- En-têtes HTTP : `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`
  (caméra limitée à l'app pour le scan), `Cross-Origin-Opener-Policy`, HSTS en HTTPS, en-tête `Server` retiré.
- Logs d'accès Caddy **sans** `Cookie`, `Authorization` ni `Set-Cookie`.
- Réponses API jamais mises en cache par le service worker (route `NetworkOnly` sur le chemin `/api/*`).
- Chunks `/_nuxt` manquants : simple 404, jamais une page HTML mise en cache un an.
- Image de production **non-root**, sans Composer ni git ni fichiers de dev ; démarrage refusé sans `APP_SECRET`.
- Ports de dev (PostgreSQL, Mailpit) liés à `127.0.0.1`.
- CI : actions tierces épinglées par SHA, `persist-credentials: false`, permissions `contents: read`.
- **Authentification (phase 1)** : sessions serveur (PostgreSQL) dans un cookie `httpOnly`, `SameSite=Lax`, `Secure` en HTTPS,
  identifiant régénéré à la connexion ; chaque session HTTP est liée à un appareil révocable, vérifié à chaque requête.
- Mots de passe : `auto` (Argon2id/bcrypt), 12 caractères minimum ; comparaison factice quand l'email est inconnu (pas d'énumération par le temps).
- Liens email : secrets de 256 bits, stockés hachés, usage unique, durée courte ; aucune réponse ne révèle l'existence d'un compte.
- CSRF : contrôle `Origin` / `Sec-Fetch-Site` sur toutes les requêtes mutantes de l'API, en plus de `SameSite=Lax`.
- Limitation de débit (429 + `Retry-After`) : connexion par IP+email, par IP et **par compte** (tentatives réparties sur
  plusieurs IP), inscription par IP, liens email par IP et par adresse, second facteur par compte, et **ressaisie du mot de passe
  depuis une session ouverte** (changement de mot de passe, 2FA) par compte : un cookie volé ne permet pas de deviner le mot de passe.
- Emails envoyés après la réponse : une demande de réinitialisation répond dans le même temps que le compte existe ou non.
- Une liaison Google à un compte existant déconnecte tous ses appareils (celui qui aurait créé le compte avant son propriétaire perd l'accès).
- Verrou optimiste sur les valeurs à usage unique (code TOTP, code de secours, lien) : deux requêtes simultanées ne peuvent pas les utiliser deux fois.
- Les appareils inactifs depuis 30 jours sont déconnectés ; le cookie de session est renouvelé une fois par jour (identifiant changé).
- Logs d'accès : paramètres `token`, `code` et `state` masqués.
- L'inscription répond 409 pour un email déjà utilisé : c'est une énumération assumée (inscription fermée en production, 5 / h par IP).
- Redirection après connexion limitée aux chemins internes, même règle et même table de tests côté API et PWA
  (refus de `//x`, `/\x` et de tout caractère de contrôle, que les navigateurs suppriment : `/%09/x` → `//x`).
- **2FA** : graine TOTP chiffrée (libsodium secretbox, clé `APP_ENCRYPTION_KEY`), anti-rejeu par pas de temps,
  codes de secours de ≈ 50 bits stockés en hachage à clé (BLAKE2b, sous-clé dérivée), essais limités par compte.
  Le QR code est dessiné localement : la graine ne transite par aucun service tiers.
- **OAuth / OIDC** : flux « authorization code » + PKCE (S256) + `state` en session serveur, échange du code côté serveur
  (aucun jeton dans le navigateur) ; redirection de retour limitée aux chemins internes ; liaison automatique seulement
  entre emails vérifiés des deux côtés ; le faux fournisseur de test n'existe pas dans l'image de production (vérifié en E2E).
- Isolation vérifiée : sessions d'un autre utilisateur invisibles et non révocables (404) ; E2E d'alternance de deux utilisateurs sur un seul worker FrankenPHP.
- Pas de stack trace en production (problem+json) ; Swagger UI désactivé en production.
- Secrets : aucun dans le dépôt (`.env.example` sans valeur sensible), scan `gitleaks` en CI,
  audit des dépendances Composer/pnpm en CI, Dependabot hebdomadaire.
- Aucun appel direct du frontend vers la base, l'IA, Open Food Facts ou le stockage privé : tout passe par Symfony.

## Prévu

| Sujet | Phase |
| --- | --- |
| Adapter Apple Sign In (nécessite un compte développeur Apple, ADR-0016) | à la demande |
| Lier / délier un fournisseur externe depuis les Réglages | à la demande |
| Rotation de `APP_ENCRYPTION_KEY` (préfixe de version `v1:` prévu) | 10 |
| Vérification des mots de passe compromis (k-anonymity HIBP), re-hash à la connexion si l'algorithme évolue | à décider |
| Contrôle d'accès par propriétaire sur chaque nouvelle ressource, tests d'isolation | chaque phase |
| Photos en bucket privé, URLs signées courte durée | 5/8 |
| Données minimales envoyées aux providers IA, provider journalisé | 6 |
| Audit des actions sensibles, export et suppression de compte | 8 |
| Content-Security-Policy (l'`index.html` généré contient un script inline : hashes ou nonces nécessaires) | 7 |
| Désactiver l'API admin Caddy (`localhost:2019`) ou la restreindre, en prévision des appels sortants (IA, Open Food Facts) | 3 |

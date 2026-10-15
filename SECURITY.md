# Sécurité

## Signaler une vulnérabilité

Le dépôt est public, l'application ne l'est pas. Signaler une vulnérabilité **en privé** : onglet *Security* du dépôt →
*Report a vulnerability* (signalement privé GitHub). Ne pas ouvrir d'issue publique.

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
- **Content-Security-Policy** : la page de l'app ne charge qu'elle-même (scripts en ligne par empreinte, aucun style en ligne,
  photos depuis le bucket), n'est encadrée nulle part ; les réponses de l'API (données) ne peuvent rien charger
  (`default-src 'none'`, erreurs comprises). `Cross-Origin-Resource-Policy: same-origin`. Vérifié sur l'image de production,
  ainsi qu'une navigation sans aucune violation de la politique (`pwa/e2e/security.spec.ts`).
- **Tests de sécurité (phase 9)** :
  - matrice d'accès tirée de l'OpenAPI (`api/tests/Api/Security/AccessMatrixTest.php`) : chaque opération répond 401
    déconnectée (sauf la liste publique justifiée), 403 depuis un autre site, et 404 sur la ressource d'un autre
    utilisateur, dont les données restent intactes ; une route ajoutée échoue tant qu'elle n'est pas classée, et les
    contrôleurs hors spec aussi ;
  - chaque limiteur d'écriture par compte répond 429 avec `Retry-After` ;
  - envois de fichiers piégés refusés avant décodage (SVG, HTML, bombe de pixels, animation, taille), polyglotte réencodé
    sans sa charge, métadonnées GPS retirées ;
  - cookie de session `Secure`, `HttpOnly`, `SameSite=Lax`, erreurs sans trace, sur l'image de production ;
  - **OWASP ZAP** en CI (`make zap`, appelé par `make e2e-prod`) : analyse passive de l'app et de chaque opération de
    l'API connectée (cookie d'un compte créé pour l'occasion) ; échec sur toute alerte moyenne ou haute non justifiée
    dans `zap/rules.tsv`. L'analyse active (attaques) n'est pas en CI : à lancer avant une mise en production
    (`zap-api-scan.py` sans `-S`, sur une stack jetable).

- **Production (phase 10, ADR-0033)** :
  - aucun port ouvert sur Internet : SSH, l'application et Uptime Kuma ne sont joignables que par le tailnet Tailscale ;
    la politique d'accès n'admet que les appareils du propriétaire (et le SSH de la CI) et ne laisse rien sortir du
    serveur ; les en-têtes d'identité `Tailscale-User-*` ne sont pas journalisés ;
  - l'image est construite une fois par la CI, attestée (provenance), testée sur ce digest puis signée (cosign sans
    clé) ; le serveur refuse toute image non signée par le workflow `ci.yml` de ce dépôt sur `main`, et la production
    toute image qui n'a pas tourné en staging ;
  - chaque clé de la CI ne peut lancer qu'une commande (`bulkpilot-gate` : `deploy <commit>` ou `status`, pour son
    seul environnement) ; scripts et fichier compose du serveur possédés par root ; production derrière un relecteur
    obligatoire ;
  - staging isolé : ses secrets, ses buckets, sa base, son propre nom d'hôte (cookies jamais partagés) ; `TRUSTED_HOSTS`
    et `TRUSTED_PROXIES` limités à l'hôte public et au sous-réseau du proxy ;
  - hôte : mises à jour de sécurité automatiques, SSH par clé seulement, pare-feu rejoué au démarrage ;
  - sauvegardes chiffrées hors du serveur, bucket verrouillé 30 jours, secrets réservés au worker ;
  - sous-traitants : Oracle (hébergement, UE), Cloudflare R2 (photos et sauvegardes chiffrées, juridiction UE),
    Brevo (emails), Sentry (erreurs nettoyées des données personnelles, région UE), healthchecks.io (battements
    seulement : aucune donnée de compte), Tailscale (réseau ; le trafic est chiffré de bout en bout).

## Dépendances

- La CI bloque sur `composer audit --locked` et sur `pnpm audit --prod`. Côté PWA, « prod » veut dire ce qui part
  dans le SPA compilé (Vue, le routeur, les icônes, la police, `ofetch`, `uqr`, `zxing-wasm`) ; Nuxt et ses modules
  sont des outils de build, déclarés en `devDependencies`, et l'image de production ne sert que des fichiers statiques.
- Les versions corrigées de dépendances indirectes sont imposées par `overrides` dans `pwa/pnpm-workspace.yaml`
  quand le paquet qui les tire tarde à monter (`sharp`, `shell-quote`, `source-map-js`).
- Restent connues, sans version corrigée publiée (octobre 2026), et seulement dans l'outillage de développement : `simple-git` (via `@nuxt/devtools`), `node-forge` (serveur de dev `listhen`), `braces` (recherche
  de fichiers pendant le build). Elles n'existent que sur un poste de développement ou pendant la construction de
  l'image ; Dependabot proposera la montée dès qu'elle existe. `pnpm audit` (sans `--prod`) les liste.

## Prévu

| Sujet | Phase |
| --- | --- |
| Adapter Apple Sign In (nécessite un compte développeur Apple, ADR-0016) | à la demande |
| Lier / délier un fournisseur externe depuis les Réglages | à la demande |
| Rotation de `APP_ENCRYPTION_KEY` (préfixe de version `v1:` prévu) | 10 |
| Vérification des mots de passe compromis (k-anonymity HIBP), re-hash à la connexion si l'algorithme évolue | à décider |
| Contrôle d'accès par propriétaire : couvert d'office par la matrice d'accès (une route non classée fait échouer les tests) | fait (9) |
| Photos en bucket privé, URLs signées courte durée | 5/8 |
| Données minimales envoyées aux providers IA, provider journalisé | 6 |
| Audit des actions sensibles, export et suppression de compte | 8 |
| Désactiver l'API admin Caddy (`localhost:2019`) ou la restreindre, en prévision des appels sortants (IA, Open Food Facts) | 3 |

# Déploiement

La production tourne **gratuitement** sur une VM Oracle Cloud Always Free (Ampere, arm64), privée derrière Tailscale,
avec PostgreSQL dans Docker sur la VM, les photos et les sauvegardes chiffrées sur Cloudflare R2, l'email par Brevo,
les erreurs dans Sentry, la disponibilité dans Uptime Kuma et les battements dans healthchecks.io. Les choix, leurs
limites et comment en sortir : ADR-0033. Ce document est le mode d'emploi, de la création des comptes aux incidents ;
la supervision a le sien : [MONITORING.md](MONITORING.md).

## Vue d'ensemble

```text
git push main ─► CI : qa, mutation, charge, scripts du serveur
                      image ─ construite UNE fois (amd64 + arm64), poussée sur GHCR par digest, provenance attestée
                      e2e   ─ sur ce digest exact (E2E, sauvegardes, ZAP, Lighthouse)
                      sign  ─ tout est vert : l'image est signée (cosign, identité du workflow ci.yml sur main)
                  │
                  ▼
release.yml ── runner dans le tailnet (Tailscale éphémère, tag:ci) ── ssh « deploy <commit> » ──► bulkpilot-gate staging
                                         VM : tire ghcr…:sha-<commit>, vérifie la signature, note le digest, déploie
                                         https://bulkpilot-staging.<tailnet>.ts.net
deploy-production.yml (à la main, relecteur obligatoire) ── ssh ──► bulkpilot-gate production
                                         VM : reprend LE digest qu'a tourné staging, sauvegarde, déploie
                                         https://bulkpilot.<tailnet>.ts.net

VM : tailscale serve (HTTPS) ─► 127.0.0.1:8080 production (Caddy + Symfony + PWA) ; :10000 ─► Uptime Kuma
     staging : son propre nœud Tailscale (conteneur) ─► son php ; PostgreSQL par environnement
     worker ─► sauvegardes chiffrées R2 bulkpilot-backups ; battements ─► healthchecks.io
     photos ─► R2 bulkpilot-photos (liens présignés 5 min) ; emails ─► Brevo ; erreurs ─► Sentry
```

Le serveur ne construit rien et n'exécute que des images signées par la CI de ce dépôt. Rien n'écoute sur Internet :
SSH et HTTPS passent par le tailnet (tes appareils seulement). Le téléphone l'atteint en Wi-Fi comme en 4G, avec
l'application Tailscale.

## Coût

**0 €** dans les limites gratuites : VM Always Free (4 OCPU, 24 Go, 200 Go de disque), R2 (10 Go, opérations
incluses, pas de frais de sortie), Brevo (300 emails / jour), Sentry Developer (5 000 erreurs / mois), healthchecks.io
(20 contrôles), Tailscale Personal, GitHub Actions et GHCR (gratuits pour un dépôt public). Ce qui coûterait : plus de
10 Go de photos et sauvegardes (R2 ~0,015 $/Go/mois), un domaine (~10 €/an, pour aligner SPF/DKIM de l'email ou ouvrir
l'application au public), une VM payante si Oracle manque de capacité.

## 1. Créer les comptes (une fois)

Garder chaque secret dans un gestionnaire de mots de passe au fur et à mesure.

### 1.1 GitHub

Un dépôt **public** (Actions et GHCR gratuits sans limite ; aucun secret n'est dans le code — ils vivent dans
GitHub et sur le serveur). Les workflows sont dans `.github/workflows`. Installer `gh` sur l'ordinateur et
`gh auth login`.

### 1.2 Oracle Cloud

cloud.oracle.com → *Sign up* (une carte est demandée pour vérifier l'identité, rien n'est débité), région d'origine
**France Central (Paris)** ou **France South (Marseille)** — elle ne se change plus.

1. **Billing & Cost Management → Upgrade and Manage Payment → Pay As You Go** (recommandé) : toujours gratuit dans les
   limites Always Free, et l'instance n'est plus récupérable pour inactivité.
2. **Billing & Cost Management → Budgets → Create Budget** : montant 1 €, alerte à 100 % des coûts *réels*, ton email.
   Le moindre centime facturé te prévient.
3. **Compute → Instances → Create instance** :
   - *Image and shape* → *Change image* → **Canonical Ubuntu 24.04** ; *Change shape* → **Ampere** →
     **VM.Standard.A1.Flex**, 4 OCPU, 24 Go (le libellé « Always Free-eligible » doit apparaître) ;
   - *Networking* : créer un VCN et un sous-réseau **public**, *Assign a public IPv4 address* (pour le premier accès
     seulement) ;
   - *Add SSH keys* → *Upload public key files* : ta clé (`~/.ssh/id_ed25519.pub`) ; c'est elle qui ouvrira aussi le
     compte `deploy` ;
   - *Boot volume* → *Specify a custom boot volume size* : 100 Go (200 Go gratuits au total).
4. **« Out of host capacity »** : l'Ampere gratuit manque souvent. Réessayer à d'autres heures (tôt le matin) ou dans
   un autre *availability domain* s'il y en a ; à défaut, créer en 1 OCPU / 6 Go puis *Edit → Shape* pour monter à
   4 / 24 quand la capacité revient. Passer en *Pay As You Go* aide aussi.

Noter l'IP publique de l'instance et l'utilisateur `ubuntu`.

### 1.3 Tailscale

tailscale.com, offre **Personal** : se connecter, installer l'application sur l'iPhone et l'ordinateur. Dans la
console d'administration, **avant** de préparer le serveur :

- **DNS** : activer **MagicDNS**, puis **HTTPS Certificates** (*Enable HTTPS*). Les noms des machines servies en
  HTTPS (`bulkpilot.<tailnet>.ts.net`, `bulkpilot-staging.…`) deviennent **publics** dans les journaux de
  transparence des certificats : rien n'y est joignable depuis Internet, mais ne pas choisir de nom qui en dise plus.
- **Access controls** : remplacer la politique par défaut (tout le monde vers tout) par :

  ```json
  {
    "tagOwners": {
      "tag:server": ["autogroup:admin"],
      "tag:ci": ["autogroup:admin"]
    },
    "acls": [
      {"action": "accept", "src": ["autogroup:member"], "dst": ["tag:server:22,443,10000", "autogroup:self:*"]},
      {"action": "accept", "src": ["tag:ci"], "dst": ["tag:server:22"]}
    ],
    "ssh": []
  }
  ```

  Tes appareils joignent le serveur (SSH, l'application, Uptime Kuma) et se joignent entre eux ; la CI ne joint que le
  SSH du serveur ; **rien ne part du serveur** vers tes appareils (une intrusion y reste enfermée).
- **Settings → Keys → Generate auth key** : deux clés **non réutilisables**, **pré-approuvées**, étiquette
  `tag:server` : une pour la VM (`TAILSCALE_AUTHKEY` de `bootstrap.sh`), une pour le nœud de staging (`TS_AUTHKEY` de
  `staging.env`).
- **Settings → OAuth clients → Generate OAuth client** : portée **Auth Keys → Write**, étiquette `tag:ci` ; son id et
  son secret vont dans GitHub (§4).

### 1.4 Cloudflare R2

dash.cloudflare.com → **R2** (un moyen de paiement est demandé pour activer R2, l'usage reste dans l'offre gratuite) :

- **Create bucket**, quatre fois, *Location* → **Specify jurisdiction → European Union** : `bulkpilot-photos`,
  `bulkpilot-backups`, `bulkpilot-photos-staging`, `bulkpilot-backups-staging`. Aucun accès public, aucun domaine,
  **aucune règle CORS** (le navigateur n'y écrit jamais ; il lit les photos par des liens présignés en `<img>`).
  L'endpoint de ces buckets est `https://<ACCOUNT_ID>.eu.r2.cloudflarestorage.com` (*Account ID* sur la page R2).
- Sur les deux buckets de **sauvegarde**, *Settings* :
  - **Object lifecycle rules** : supprimer après **35 jours** les objets du préfixe `db/`, après **42 jours** ceux de
    `erasures/` (staging : 12 et 19) — un filet si l'application ne supprime plus ;
  - **Bucket lock rules** : préfixe `db/`, **30 jours** (staging : 7) — une sauvegarde ne peut être ni effacée ni
    remplacée avant ce délai, même avec la clé.
  Les buckets de photos n'ont ni verrou ni versionnage : une suppression doit effacer.
- **Manage R2 API Tokens → Create API token**, quatre fois : *Object Read & Write*, **Apply to specific buckets only**
  → un seul bucket par jeton. Noter l'*Access Key ID* et le *Secret Access Key* de chacun (affichés une fois).

### 1.5 Brevo (email)

brevo.com, offre **Free** : *Senders, domains & dedicated IPs → Senders* → ajouter et vérifier ton adresse (c'est
`MAILER_FROM`). Puis *SMTP & API → SMTP* : l'**identifiant** (`xxxx@smtp-brevo.com`) et une **clé SMTP** par
environnement. `MAILER_DSN` s'écrit `smtp://xxxx%40smtp-brevo.com:<clé>@smtp-relay.brevo.com:587` (le `@` de
l'identifiant en `%40`).

Sans domaine à toi, un expéditeur Gmail, Outlook ou Yahoo **échoue à DMARC** (le message ne vient pas de leurs
serveurs) : Brevo peut réécrire l'expéditeur, et les emails arriver en indésirables — les marquer « pas un spam » une
fois. Un domaine (~10 €/an) authentifié dans Brevo règle cela.

### 1.6 Sentry (facultatif)

sentry.io, offre **Developer**, *Data storage location* → **EU** à la création de l'organisation (elle ne se change
plus). Deux projets : **PHP → Symfony** (`SENTRY_DSN`) et **Browser JavaScript** (`PWA_SENTRY_DSN` : la PWA a son
propre rapporteur, pas le SDK Vue). Dans les réglages de l'organisation, garder **Data Scrubber** et **Prevent Storing
of IP Addresses** activés. GlitchTip auto-hébergé accepte les mêmes DSN (MONITORING.md).

### 1.7 healthchecks.io et ntfy

healthchecks.io (offre gratuite, 20 contrôles) : un projet `bulkpilot`, ses contrôles et leurs périodes sont listés
dans [MONITORING.md](MONITORING.md#healthchecksio) — **server** (le serveur entier, 5 min), **scheduler**,
**backup**, **backup verify**. Leurs URL de ping (`https://hc-ping.com/<uuid>`) vont dans `server.env` et
`production.env` (§3).

**ntfy** : installer l'application sur l'iPhone, s'abonner à un sujet au nom aléatoire
(`bulkpilot-$(openssl rand -hex 12)`), puis le brancher dans healthchecks.io (*Integrations → ntfy*) et plus tard dans
Uptime Kuma.

### 1.8 Google (facultatif, connexion avec Google)

console.cloud.google.com → un projet → **APIs & Services → OAuth consent screen** : type **External**, statut
**Testing**, ton adresse en *Test users* (aucune validation Google nécessaire tant que tu es seul). Puis
**Credentials → Create credentials → OAuth client ID** → *Web application*, un client par environnement, URI de
redirection `https://bulkpilot.<tailnet>.ts.net/api/auth/oauth/google/callback` (et
`https://bulkpilot-staging.<tailnet>.ts.net/…` pour celui de staging).

## 2. Préparer le serveur

Depuis ton ordinateur, dans le dépôt. D'abord les **deux clés de la CI** (une par environnement : la clé de staging
ne peut pas déployer en production) :

```sh
ssh-keygen -t ed25519 -N '' -C ci-staging -f ./ci-staging
ssh-keygen -t ed25519 -N '' -C ci-production -f ./ci-production
scp -r deploy ubuntu@<IP publique>:~/
ssh ubuntu@<IP publique> sudo \
  IMAGE_REPO=ghcr.io/<owner>/bulkpilot-php GITHUB_REPO=<owner>/<repo> \
  STAGING_KEY="'$(cat ci-staging.pub)'" PRODUCTION_KEY="'$(cat ci-production.pub)'" \
  TAILSCALE_AUTHKEY=tskey-auth-… ./deploy/bootstrap.sh
```

`<owner>` en **minuscules** pour `IMAGE_REPO` (GHCR l'exige). `bootstrap.sh` (relançable sans risque) :

- installe Docker, Tailscale et les **mises à jour de sécurité automatiques** (dépôts Docker et Tailscale compris,
  redémarrage à 5 h UTC si besoin), 4 Go de swap, la rotation des logs ;
- durcit SSH (clés seulement, pas de root, seuls `ubuntu` et `deploy` admis) ;
- crée l'utilisateur `deploy` et `/opt/bulkpilot`, scripts **possédés par root** ; dans `~deploy/.ssh/authorized_keys`,
  chaque clé de la CI ne peut lancer **qu'une commande** (`bulkpilot-gate <env>` : `deploy <commit>` ou `status`),
  et tes clés d'`ubuntu` y sont copiées (un shell normal, pour toi) ;
- connecte la VM au tailnet (`tag:server`) puis **ferme tous les ports entrants sauf par le tailnet** (SSH public
  compris ; `KEEP_PUBLIC_SSH=1` pour le garder) ;
- publie `https://bulkpilot.<tailnet>.ts.net` (production) et `:10000` (Uptime Kuma) avec `tailscale serve`, démarre
  Uptime Kuma et le battement du serveur vers healthchecks.io (toutes les 5 min).

**Ne pas te fermer dehors** : garder cette session ouverte et vérifier, depuis un autre terminal de l'ordinateur
(dans le tailnet), `ssh ubuntu@bulkpilot` **et** `ssh deploy@bulkpilot`. Seulement ensuite :

- Oracle → **Networking → Virtual cloud networks** → le VCN → *Subnets* → le sous-réseau → *Security* → la *Default
  Security List* → *Ingress Rules* : supprimer la règle `0.0.0.0/0` TCP 22. Plus rien n'entre depuis Internet ; la
  **console série** d'Oracle (*Instance → OS management → Console connection*) reste l'accès de secours ;
- ouvrir **tout de suite** `https://bulkpilot.<tailnet>.ts.net:10000` et créer le compte d'Uptime Kuma (le premier
  venu le crée).

Organisation sur le serveur :

| Chemin | Contenu |
| --- | --- |
| `/opt/bulkpilot/bin/` | `deploy.sh`, `rollback.sh`, `restore.sh`, `prune.sh`, `bp.sh`, `lib.sh`, `compose.yaml` (root ; seul `bootstrap.sh` les change) |
| `/usr/local/sbin/bulkpilot-gate`, `bulkpilot-heartbeat`, `bulkpilot-firewall` | la porte des clés de la CI, le battement, le pare-feu (unités systemd du même nom) |
| `/opt/bulkpilot/env/server.env` | dépôt d'images, signataire attendu, URL du contrôle healthchecks.io « server » |
| `/opt/bulkpilot/env/production.env`, `staging.env` | réglages et secrets de chaque environnement (mode 600, jamais dans Git) |
| `/opt/bulkpilot/state/<env>.current`, `.previous`, `.history` | l'image en service (`<commit> <image>@sha256:…`), la précédente, l'historique |
| `/opt/bulkpilot/state/<env>.failure.log` | les logs du dernier déploiement en échec (jamais envoyés à la CI) |

**Changer les scripts du serveur** (tout fichier de `deploy/`) : `make server-update` depuis l'ordinateur (copie
`deploy/` et relance `bootstrap.sh` en root). Une version de l'application ne les change jamais.

## 3. Les réglages et secrets

`bootstrap.sh` a créé `production.env` et `staging.env` depuis `deploy/environment.env.example` (chaque ligne y est
expliquée, avec la valeur de staging en commentaire). Les remplir **sur le serveur** :

```sh
ssh deploy@bulkpilot
openssl rand -hex 32       # APP_SECRET, POSTGRES_PASSWORD (un par environnement)
openssl rand -base64 32    # APP_ENCRYPTION_KEY, BACKUP_PASSPHRASE (un par environnement)
nano /opt/bulkpilot/env/production.env
nano /opt/bulkpilot/env/staging.env
nano /opt/bulkpilot/env/server.env           # HEALTHCHECKS_HOST_URL : le contrôle « server »
grep -nE '^[A-Z_]+=$' /opt/bulkpilot/env/*.env   # ce qui reste vide (seuls Google, Sentry, les battements et TS_AUTHKEY de production peuvent l'être)
```

- `PUBLIC_HOST` : `bulkpilot.<tailnet>.ts.net` en production (affiché à la fin de `bootstrap.sh`),
  `bulkpilot-staging.<tailnet>.ts.net` en staging, avec `TS_AUTHKEY` = la clé du nœud de staging (§1.3).
- Staging n'a **aucun** secret de production : ses buckets, ses jetons R2, sa clé Brevo, ses propres secrets. Ses
  battements restent vides (ou des contrôles à part).
- Copier les **deux fichiers env** dans le gestionnaire de mots de passe : ils suffisent à reconstruire le serveur.
  La `BACKUP_PASSPHRASE` de production se garde ainsi **aussi hors du serveur** : sans elle, les sauvegardes sont
  illisibles.
- Un réglage changé plus tard s'applique par `/opt/bulkpilot/bin/bp.sh <env> up -d`.

## 4. GitHub

Depuis l'ordinateur, dans le dépôt :

```sh
# Le client OAuth Tailscale (tag:ci), commun aux deux environnements
gh secret set TS_OAUTH_CLIENT_ID
gh secret set TS_OAUTH_SECRET
# Le serveur et sa clé d'hôte (épinglée : la CI refuse tout autre serveur)
gh variable set DEPLOY_SSH_TARGET --body deploy@bulkpilot
ssh-keyscan -t ed25519 bulkpilot | gh variable set DEPLOY_KNOWN_HOSTS
gh variable set STAGING_URL --body https://bulkpilot-staging.<tailnet>.ts.net
gh variable set PRODUCTION_URL --body https://bulkpilot.<tailnet>.ts.net
# Une clé par environnement, dans son environnement GitHub
gh api -X PUT repos/<owner>/<repo>/environments/staging
gh api -X PUT repos/<owner>/<repo>/environments/production
gh secret set DEPLOY_SSH_KEY --env staging < ci-staging
gh secret set DEPLOY_SSH_KEY --env production < ci-production
shred -u ci-staging ci-production
```

Puis dans **Settings → Environments → production** : **Required reviewers** → toi, et **Deployment branches and
tags** → *Selected branches* → `main`. Un déploiement en production attend ainsi ton approbation, même lancé par
erreur.

Après la **première** CI verte sur `main` : *Profil → Packages → bulkpilot-php → Package settings → Change
visibility → Public* (le serveur la tire sans identifiant), et vérifier que le dépôt y figure dans *Manage Actions
access* avec le rôle *Write*.

## 5. Première mise en production (spec §144)

1. Pousser sur `main` : la CI construit, teste et signe l'image ; *Release to staging* la déploie. Ouvrir
   `https://bulkpilot-staging.<tailnet>.ts.net`, créer un compte de test, essayer les parcours principaux (pesée,
   repas, scan, séance, photo, export, connexion Google si configurée).
2. **Actions → Deploy to production → Run workflow**, le commit (40 caractères, affiché dans le résumé de staging),
   puis approuver. Aucune sauvegarde avant le tout premier déploiement : il n'y a encore rien.
3. Ouvrir l'inscription le temps de créer **ton** compte : `REGISTRATION_ENABLED=true` dans `production.env`,
   `bp.sh production up -d`, créer le compte depuis l'iPhone, confirmer l'email ; repasser `false`,
   `bp.sh production up -d`.
4. Activer la **double authentification** dans les réglages de l'application.
5. Importer la table CIQUAL (téléchargée depuis l'Anses, idempotent), dans chaque environnement :
   `bp.sh production exec php bin/console nutrition:catalogue:import`.
6. Une sauvegarde et sa vérification maintenant, pour ne pas attendre la nuit :
   `bp.sh production exec worker bin/console app:backup:create` puis `… app:backup:verify` ; les contrôles
   healthchecks.io « backup » et « backup verify » passent au vert.
7. Uptime Kuma : les moniteurs et les notifications de [MONITORING.md](MONITORING.md#uptime-kuma).
8. **Sur l'iPhone** : dans l'app Tailscale, activer le VPN et, dans ses réglages, **VPN On Demand** (le tunnel se
   rallume seul) ; dans Safari, `https://bulkpilot.<tailnet>.ts.net` → Partager → **Sur l'écran d'accueil** ;
   autoriser les notifications et la caméra au premier usage.
9. Vérifier une alerte de bout en bout : *Pause* puis *Resume* sur le contrôle « server » de healthchecks.io, ou
   `sudo systemctl stop bulkpilot-heartbeat.timer` 15 min — la notification ntfy arrive ; relancer le timer.

## 6. Déployer au quotidien

- Chaque commit de `main` qui passe **toute** la CI part en **staging** tout seul.
- Après vérification, **production** : Actions → *Deploy to production* → le commit → approuver. Un commit qui n'a pas
  tourné en staging est refusé, et c'est **le même digest** qui passe.
- Sans GitHub (depuis l'ordinateur, dans le tailnet) : `make server-deploy SHA=<commit> ENV=staging`, puis
  `ENV=production` (même image signée, mêmes vérifications).
- Ce qui tourne : `make server-status` ; logs : `make server-logs ENV=production SERVICE=worker` ; un shell :
  `make server-ssh`, puis `bp.sh production ps|logs|exec …`.

Ce que fait `deploy.sh` (un seul déploiement à la fois, tous environnements confondus) :

1. staging tire `ghcr.io/<owner>/bulkpilot-php:sha-<commit>` et en note le digest ; production reprend ce digest ;
   la **signature** est vérifiée (sinon : refus) ;
2. en production, une **sauvegarde chiffrée** avant tout (par le worker en service, ou un worker ponctuel s'il est
   arrêté) ; un échec arrête là ;
3. `up --wait` : `php` passe les migrations « tout ou rien », chaque service doit devenir sain, puis le rester
   (aucun redémarrage) et répondre `/api/health` ;
4. sinon, **retour automatique** à l'image précédente, workflow en échec, logs dans `state/<env>.failure.log` ;
5. les vieilles images sont supprimées (celles en service, les précédentes et les dix dernières de staging restent).

Une connexion coupée (job annulé) n'interrompt pas un déploiement commencé.

## 7. Revenir en arrière

- **Une version qui marche mais déçoit** : `/opt/bulkpilot/bin/rollback.sh production` — l'image précédente revient
  (sauvegarde d'abord), la base reste telle quelle.
- **Avec la base d'avant** (une migration a abîmé des données) :
  `rollback.sh production --restore=latest` — php et worker s'arrêtent, la base est remplacée par la dernière
  sauvegarde (celle prise juste avant le déploiement), l'image précédente redémarre dessus, les comptes effacés depuis
  le sont à nouveau. `--restore=db/AAAA/MM/JJ/HHMMSS.dump.enc` pour une sauvegarde précise. Tout ce qui a été écrit
  depuis est perdu.
- **Déployer sans sauvegarde** (R2 indisponible et un correctif urgent) : *Deploy to production* avec `skip_backup`
  coché **et** `sans sauvegarde` recopié dans `confirm_skip_backup` ; ou `deploy.sh <commit> production
  --skip-backup` sur le serveur. Faire une sauvegarde à la main dès que possible.

### Migrations : ajouter, puis retirer

Le retour en arrière remet l'**ancien code sur le nouveau schéma** : une migration ne fait qu'**ajouter** (table,
colonne nullable ou avec valeur par défaut, index). Retirer ou resserrer (supprimer, renommer, `NOT NULL` sans défaut,
changer un type) se fait **une version plus tard**, quand plus aucun code en service ne s'en sert, dans une migration
qui le dit : un commentaire `// contract: <pourquoi la version précédente n'en a plus besoin>`. `make qa` le vérifie
(`tools/check_migrations.py`, à partir de la migration `Version20261002143553`).

## 8. Supervision

Ce qui est surveillé, les moniteurs à créer dans Uptime Kuma et healthchecks.io, et quoi faire à chaque alerte :
[MONITORING.md](MONITORING.md).

## 9. Incidents

Les alertes de supervision ont leur tableau dans [MONITORING.md](MONITORING.md#quand-une-alerte-arrive). Ici, ce qui
touche au serveur et au déploiement :

| Symptôme | Où regarder | Quoi faire |
| --- | --- | --- |
| Déploiement en échec | le résumé du workflow ; `state/<env>.failure.log` sur le serveur | l'environnement est revenu à l'image précédente : corriger, repousser |
| « not signed by this repository's CI » | le job `sign` de la CI de ce commit | ne déployer que des commits dont la CI est entièrement verte |
| « never ran in staging » | `state/staging.history` | déployer d'abord ce commit en staging |
| « pre-deployment backup failed » | `bp.sh production logs worker` (`system.backup_failed`) | régler la cause (jeton R2, quota) puis redéployer ; en urgence seulement, `skip_backup` (§7) |
| « another deployment … is running » | `state/deploy.lock` | attendre la fin de l'autre ; un verrou ne survit pas à son processus |
| L'application ne répond plus | `make server-status` | `bp.sh production logs --tail=200 php` ; `bp.sh production up -d` ; si une mise à jour est en cause, `rollback.sh production` |
| Disque presque plein | `df -h`, `docker system df` | `/opt/bulkpilot/bin/prune.sh`, `docker system prune` (jamais `--volumes`) |
| La VM est perdue (récupérée par Oracle, panne) | — | voir ci-dessous |
| Une clé a fuité | — | la révoquer chez son fournisseur (R2, Brevo, Tailscale, GitHub), en mettre une nouvelle dans l'env, `bp.sh <env> up -d` ; `APP_ENCRYPTION_KEY` ne se change pas sans réenrôler la 2FA ; une clé de la CI : en générer une autre, `make server-update` avec la nouvelle clé publique (et retirer l'ancienne ligne d'`authorized_keys`) |

**Reconstruire après la perte de la VM** :

1. Nouvelle VM (§1.2), nouvelle clé Tailscale ; supprimer l'ancienne machine `bulkpilot` dans la console Tailscale
   (le nom doit être libre).
2. `bootstrap.sh` comme au §2 (mêmes clés de la CI), puis remettre `production.env`, `staging.env` et `server.env`
   depuis le gestionnaire de mots de passe ; mettre à jour `DEPLOY_KNOWN_HOSTS` (§4).
3. Déployer le dernier commit en staging puis en production (Actions, ou `make server-deploy`) : production démarre
   sur une base vide.
4. Restaurer la dernière sauvegarde et réappliquer les effacements :

   ```sh
   ref="$(cut -d' ' -f2 /opt/bulkpilot/state/production.current)"
   /opt/bulkpilot/bin/restore.sh production "$ref" latest
   /opt/bulkpilot/bin/bp.sh production up -d
   /opt/bulkpilot/bin/bp.sh production exec worker bin/console app:backup:reapply-erasures
   ```

**Exercice de restauration** (tous les trimestres, en plus du contrôle automatique hebdomadaire) :
`rollback.sh staging --restore=latest` restaure la dernière sauvegarde **de staging** sur staging ; pour la production,
récupérer la sauvegarde sur l'ordinateur dans une pile jetable (`app:backup:fetch`), la restaurer, vérifier ses
dernières données, supprimer le dump. Les données de production ne vont jamais en staging.

## Artefact

Une **image unique** `ghcr.io/<owner>/bulkpilot-php` (target `frankenphp_prod`, amd64 et arm64) contient :
FrankenPHP/Caddy, l'API Symfony (sans dépendances ni outils de dev — ni Composer ni git —, autoloader optimisé,
opcache préchargé) et la SPA Nuxt compilée (`/app/pwa`, servie en statique avec cache immuable sur `/_nuxt/*`).
Elle s'exécute en utilisateur **non-root** (`app`), avec seulement le droit d'ouvrir les ports 80/443.
Son healthcheck interroge PHP (`/api/health` sur un listener interne `127.0.0.1:2080`).

Elle porte le commit dont elle vient (`APP_RELEASE=sha-<commit>`, et `NUXT_PUBLIC_RELEASE` pour la PWA, repris par
les rapports d'erreur). Elle est **construite une fois par la CI**, attestée (provenance GitHub), testée de bout en
bout sur ce digest exact puis **signée** (cosign, sans clé : l'identité est le workflow `ci.yml` sur `main`) ; le
serveur la tire par digest et vérifie la signature (ADR-0033). GHCR garde ses 180 dernières versions.

```bash
docker compose -f docker-compose.yml build    # la construire en local
make ci-prod                                   # la tester de bout en bout (E2E, sauvegardes, ZAP, Lighthouse)
make deploy-test                               # rejouer les scripts du serveur sur un serveur jetable local
```

## Variables de production

Elles vivent dans `/opt/bulkpilot/env/<env>.env` sur le serveur (modèle commenté : `deploy/environment.env.example`) ;
`deploy/compose.yaml` les passe aux conteneurs et fixe le reste.

| Variable | Rôle |
| --- | --- |
| `PUBLIC_HOST` | l'hôte vu par le navigateur (`bulkpilot.<tailnet>.ts.net`, `bulkpilot-staging.…`) : en dérivent `DEFAULT_URI`, `TRUSTED_HOSTS` et les en-têtes transmis à Symfony |
| `TS_AUTHKEY` | staging seulement : la clé Tailscale de son propre nœud (vide en production) |
| `APP_SECRET` | secret Symfony (signature) — obligatoire : le conteneur refuse de démarrer sans |
| `APP_ENCRYPTION_KEY` | 32 octets en base64, chiffre les graines 2FA — obligatoire ; le changer invalide les authentificateurs enrôlés |
| `POSTGRES_PASSWORD` | la base PostgreSQL de l'environnement (service `database`, sur le serveur) ; utilisateur et base `app` |
| `POSTGRES_SHARED_BUFFERS` / `POSTGRES_EFFECTIVE_CACHE_SIZE` | mémoire de PostgreSQL (1 Go / 4 Go par défaut ; staging : 256 Mo / 1 Go) |
| `EXTERNAL_DATABASE` / `DATABASE_URL` | `1` et l'URL complète pour une base managée à la place du service `database` (le compte doit pouvoir `CREATEDB`) |
| `REGISTRATION_ENABLED` | `false` en production une fois le compte du propriétaire créé |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | connexion Google (facultatif) ; URI de redirection : `https://<PUBLIC_HOST>/api/auth/oauth/google/callback` |
| `MAILER_DSN` | SMTP Brevo (§1.5) — obligatoire (le conteneur refuse `null://`) |
| `MAILER_FROM` | l'expéditeur vérifié dans Brevo — obligatoire |
| `OPEN_FOOD_FACTS_USER_AGENT` | `NomApp/Version (contact)` : Open Food Facts demande à être contacté en cas d'abus |
| `S3_ENDPOINT` / `S3_BUCKET` / `S3_ACCESS_KEY` / `S3_SECRET_KEY` | bucket R2 **privé** des photos (ADR-0027) et son jeton |
| `S3_REGION` / `S3_PATH_STYLE` / `S3_PUBLIC_ENDPOINT` | `auto`, `true` et vide pour R2 (valeurs par défaut) |
| `BACKUP_PASSPHRASE` | chiffre les sauvegardes — obligatoire, 32 caractères au moins ; à garder **aussi hors du serveur** |
| `BACKUP_S3_ENDPOINT` / `BACKUP_S3_BUCKET` / `BACKUP_S3_ACCESS_KEY` / `BACKUP_S3_SECRET_KEY` | bucket **distinct de celui des photos** (refusé sinon), avec son propre jeton |
| `BACKUP_RETENTION_DAYS` | jours de sauvegardes gardés (31, plus que le verrou du bucket ; staging 8) ; la plus récente n'est jamais supprimée |
| `SENTRY_DSN` / `PWA_SENTRY_DSN` | rapports d'erreurs de l'API et de la PWA (MONITORING.md) ; vides = éteints |
| `HEARTBEAT_URL_SCHEDULER` / `HEARTBEAT_URL_BACKUP` / `HEARTBEAT_URL_BACKUP_VERIFY` | contrôles healthchecks.io appelés par le worker (MONITORING.md) ; vides = éteints |
| `AUDIT_RETENTION_DAYS` | jours pendant lesquels l'activité du compte (journal d'audit) est gardée (365) |
| `RATE_LIMIT_*` | seuils anti-abus (valeurs par défaut sûres dans `api/.env`) |

Fixées par `deploy/compose.yaml` (ne pas les mettre dans l'env) : `SERVER_NAME=:80` (HTTP sur la boucle locale, le
HTTPS est terminé par Tailscale), `TRUSTED_PROXIES` (127.0.0.1 et le sous-réseau fixe du projet compose,
10.231.10.0/24 en production, 10.231.11.0/24 en staging), `TRUSTED_HOSTS`, `DEFAULT_URI`, `SESSION_COOKIE_NAME`
(`sid_production`, `sid_staging`), `SENTRY_ENVIRONMENT`, `MEDIA_STORAGE=s3`, `FOOD_DATABASE=openfoodfacts`,
`BACKUP_ENABLED=1` et `RUN_MIGRATIONS=0` sur le worker, `BACKUPS_MONITORED=1`, les limites de staging (1,5 Go et
1 CPU par conteneur). `APP_RELEASE` vient de l'image.

Bucket des photos : privé, **sans versionnage ni verrouillage d'objet** (une suppression doit effacer). Les envois font
16 Mo au plus (`upload_max_filesize`).

## Worker

Un service `worker` (même image, mêmes variables, plus les secrets des sauvegardes qu'il est le seul à recevoir)
consomme tous les transports sauf `failed` (`messenger:consume --all --exclude-receivers=failed` : un nouveau programme
est consommé sans toucher à la commande) (ADR-0029) : exports, effacements de comptes et de fichiers, vérifications des
notifications toutes les 15 minutes, analyse nocturne du Coach vers 3 h dans le fuseau de chaque compte. Il démarre
après `php` (qui passe les migrations), s'arrête toutes les heures ou au-delà de 192 Mo et est relancé par
`restart: unless-stopped`. Sa sonde lit l'âge de `var/worker.alive`, qu'il touche à chaque tour de boucle.

- **Un seul worker.** L'état des programmes (dernier passage) et leur verrou sont locaux au conteneur (cache fichiers,
  `flock`) : deux workers lanceraient chacun les programmes. Ne lancer qu'un `worker`.
- **Échecs.** Un message en échec est réessayé après 10 s, 30 s puis 90 s, puis rangé dans la file `failed` :
  `bp.sh production exec worker bin/console messenger:failed:show` pour les voir, `messenger:failed:retry` pour les
  rejouer (un par un, ou `--force` pour tous), `messenger:failed:remove <id>` pour en retirer un. Rejouer est sûr :
  chaque message est idempotent. Une suppression de fichier en échec (stockage indisponible) garde là les clés à
  supprimer : la rejouer, ne pas la retirer. La file peut porter l'identifiant d'un compte effacé : la vider une fois
  les messages rejoués.
- Sans worker, l'application fonctionne : seules les vérifications à l'ouverture et l'analyse à l'ouverture du Coach
  tournent, et les notifications ne sont pas poussées (elles restent dans l'application).

## Sauvegardes

Chaque nuit à 02 h 30 UTC, le worker sauvegarde la base (`pg_dump`, chiffré, envoyé au bucket `BACKUP_S3_BUCKET` sous
`db/AAAA/MM/JJ/HHMMSS.dump.enc`) puis supprime celles de plus de `BACKUP_RETENTION_DAYS` jours ; chaque dimanche à
04 h 30 UTC, il restaure la dernière dans une base jetable et la contrôle (ADR-0031). Un échec est journalisé en erreur
(`system.backup_failed`, `system.backup_verification_failed`) et signalé à healthchecks.io. À la main :

```sh
/opt/bulkpilot/bin/bp.sh production exec worker bin/console app:backup:create   # une sauvegarde maintenant
/opt/bulkpilot/bin/bp.sh production exec worker bin/console app:backup:verify   # la restaurer dans une base jetable et la contrôler
```

Les variables `BACKUP_*` ne vont qu'au `worker` (le conteneur `php` refuse de démarrer s'il a la phrase de passe ou la
clé secrète) : une intrusion par le serveur web ne peut pas les lire. Le **verrou** du bucket (§1.4) empêche d'effacer
ou de remplacer une sauvegarde de moins de 30 jours, même avec la clé du worker ; la suppression par l'application
d'une sauvegarde encore verrouillée échoue sans conséquence (elle est journalisée). Le compte PostgreSQL de
l'application a le droit `CREATEDB` (celui de l'image `postgres` l'a) pour la base de contrôle.

Les données d'un compte effacé restent dans les sauvegardes jusqu'à `BACKUP_RETENTION_DAYS` jours ; les effacements
sont retenus sous `erasures/` pour qu'une restauration ne les annule pas (`app:backup:reapply-erasures`, ci-dessous).

### Restaurer

Remplace **toute** la base de l'environnement par une sauvegarde : les écritures faites depuis sont perdues. Testé de
bout en bout par `make deploy-test`.

- Avec retour à l'image précédente : `rollback.sh <env> --restore=latest` (§7) — tout est fait, effacements compris.
- Sur l'image en service :

  ```sh
  ref="$(cut -d' ' -f2 /opt/bulkpilot/state/production.current)"
  /opt/bulkpilot/bin/restore.sh production "$ref" latest      # ou une clé db/AAAA/MM/JJ/HHMMSS.dump.enc
  /opt/bulkpilot/bin/bp.sh production up -d                    # les migrations plus récentes passent au démarrage
  /opt/bulkpilot/bin/bp.sh production exec worker bin/console app:backup:reapply-erasures
  ```

`restore.sh` arrête `php` et le worker, déchiffre la sauvegarde dans un dossier temporaire (supprimé quoi qu'il
arrive), vide le schéma et y restaure le dump. **Réappliquer les effacements avant de rouvrir l'accès** : les comptes
supprimés depuis la sauvegarde reviennent avec elle. Contrôler ensuite (connexion, dernières données attendues).

## En-têtes de sécurité

La PWA de production est servie avec une `Content-Security-Policy` stricte (Caddyfile, `pwa_static`) : ses scripts et,
par leur empreinte, les scripts en ligne de sa construction (calculées au build de l'image, `frankenphp/csp-hashes.php`) ;
les images et les liens du bucket des photos (origine calculée par l'entrypoint depuis `S3_PUBLIC_ENDPOINT`, `S3_BUCKET`
et `S3_PATH_STYLE`) ; l'envoi des rapports d'erreur vers l'hôte de `PWA_SENTRY_DSN` (`connect-src`, calculé par
l'entrypoint, absent quand le DSN est vide) ; la compilation WebAssembly du lecteur de codes-barres de secours
(`'wasm-unsafe-eval'`) ; aucun cadre (`frame-ancestors 'none'`), aucun plugin, des formulaires vers elle seule.
Les en-têtes d'identité que Tailscale ajoute (`Tailscale-User-*`) ne sont pas journalisés.
`ALLOW_SHARED_BACKUP_KEY=1` n'existe que pour la pile E2E (une passerelle S3 à une seule clé) : jamais en production.

## Notes d'exploitation

- Produits Open Food Facts : le cache (`cache.app`, 1 jour par produit) et le budget de 90 lectures / min sont **par
  instance**.
- Chaque requête authentifiée ouvre sa propre connexion PostgreSQL pour la session (en plus de Doctrine) : la base
  accepte 60 connexions, assez pour les threads de FrankenPHP, le worker et la base de contrôle.
- Les emails partent après la réponse ; une panne SMTP est journalisée (`Email could not be sent`) sans échouer la
  requête.

## Limites connues

- Un seul serveur, sans haute disponibilité : une panne de la VM coupe l'application jusqu'à sa reconstruction (la
  dernière sauvegarde nocturne au pire, ou celle d'avant le dernier déploiement).
- **Coupure pendant un déploiement** : les conteneurs sont recréés, l'application est indisponible de quelques
  secondes à une minute (migrations comprises) ; pendant ce temps, Tailscale répond par sa propre page d'erreur 502.
  La PWA installée garde ses écrans et ses données hors ligne et réessaie.
- Migrations au démarrage de `php` (une seule instance) ; avec plusieurs répliques, un job de release unique.
- Limiteurs de débit et verrous locaux au conteneur (`LOCK_DSN=flock`) : à partager (base ou Redis) avant d'ajouter une
  réplique.
- Rétention des logs : 10 Mo × 5 fichiers par conteneur (rotation Docker) ; les logs d'audit nomment des identifiants
  de compte, ceux d'un compte effacé par son pseudonyme.
- `/api/health?scope=monitoring` et `/api/client-config` sont publics dans le tailnet : à réserver avant toute
  ouverture au public (MONITORING.md).

Les environnements `local`, `staging` et `production` ont des données strictement séparées ; aucune donnée de
production n'est copiée vers un autre environnement. Les données de démonstration ne sont jamais chargées en production
(ADR-0032).

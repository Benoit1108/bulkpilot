# 0033 — Plateforme de production gratuite : une VM Ampere derrière Tailscale, des images signées par la CI

**Contexte.** Spec §144 et phase 10 : VPS, Docker, FrankenPHP, PostgreSQL, stockage objet S3-compatible, HTTPS,
monitoring, sauvegardes, CI/CD, staging puis production après validation (GitHub → CI → staging → validation →
production). Décision du propriétaire : **gratuit au départ**, un seul utilisateur, les meilleures options possibles.
Les données sont des données de santé (poids, mensurations, photos) : la confidentialité passe avant l'ouverture au
public. La revue de fin de phase a ajouté : ce qui tourne en production doit être exactement ce que la CI a testé, et
une clé de déploiement volée ne doit pas permettre de faire tourner autre chose.

**Décision.**

| Besoin | Choix | Pourquoi | Sortie |
| --- | --- | --- | --- |
| Serveur | **Oracle Cloud Always Free**, Ampere A1 (arm64, jusqu'à 4 OCPU / 24 Go), Ubuntu 24.04, région Paris ou Marseille, compte en *Pay As You Go* | la seule VM gratuite assez grande pour toute la pile (deux environnements, leurs bases, Uptime Kuma) | n'importe quel VPS Docker (amd64 compris : l'image est multi-architecture) ; `bootstrap.sh` est générique Ubuntu |
| Réseau, HTTPS | **Tailscale** (offre personnelle) : la VM rejoint le tailnet (`tag:server`), `tailscale serve` termine le HTTPS avec un vrai certificat `*.ts.net` et relaie en HTTP vers Caddy sur la boucle locale ; la politique d'accès ne laisse entrer que les appareils du propriétaire et le SSH de la CI, et ne laisse rien sortir du serveur | **aucun port ouvert sur Internet** (SSH compris) ; l'iPhone y accède en Wi-Fi comme en 4G ; pas de domaine à acheter | public plus tard : Tailscale Funnel, ou Cloudflare Tunnel + un domaine |
| Dépôt et image | dépôt **public** ; l'image est **construite une fois par la CI** (amd64 et arm64 sur des runners natifs), poussée sur **GHCR** par digest avec une attestation de provenance, testée de bout en bout sur ce digest (E2E, sauvegardes, ZAP, Lighthouse), puis **signée** (cosign sans clé, identité OIDC du workflow `ci.yml` sur `main`) quand tout est vert | ce qui tourne est ce qui a été testé, au bit près ; GHCR et les runners arm64 sont gratuits pour un dépôt public ; le serveur ne construit rien et n'a besoin d'aucun identifiant de registre | un registre privé (identifiants en lecture sur le serveur) ; la vérification de signature reste |
| Déploiement sur le serveur | le serveur **tire par digest et vérifie la signature** ; staging tire `sha-<commit>` et en note le digest, production ne prend **que** ce digest | une image non signée par ce dépôt, ou jamais passée en staging, est refusée | — |
| Accès de la CI | une clé SSH **par environnement** (secret de l'environnement GitHub), qui ne peut lancer qu'une commande : `bulkpilot-gate <env>` accepte `deploy <commit> [--skip-backup]` et `status` ; scripts et fichier compose possédés par root, installés par `bootstrap.sh` seul | une clé volée ne déploie qu'une image signée, dans son environnement, et ne lit ni secret ni log ; une version de l'application ne peut pas changer ce qui la déploie | — |
| CI/CD | GitHub Actions : après la CI sur `main`, **staging automatique** (`release.yml`) ; **production à la main** (`deploy-production.yml`, le commit en entrée) avec un **relecteur obligatoire** et la branche `main` seule sur l'environnement `production` ; les runners rejoignent le tailnet (client OAuth Tailscale, nœuds éphémères `tag:ci`) | la « validation » de la spec est l'approbation du déploiement, refusé pour un commit qui n'a pas réussi en staging | — |
| PostgreSQL | **PostgreSQL 18 dans Docker sur la VM**, un par environnement (volume nommé), réglé pour sa part des 24 Go | les offres managées gratuites (Neon, Supabase, Aiven) se mettent en veille, plafonnent à 0,5–1 Go et ajoutent la latence d'un autre cloud ; la durabilité vient des sauvegardes chiffrées hors du serveur (ADR-0031), de leur restauration hebdomadaire et de celle prise avant chaque déploiement | une base managée : `EXTERNAL_DATABASE=1` et `DATABASE_URL` (le compte doit pouvoir `CREATEDB`) |
| Stockage objet | **Cloudflare R2**, juridiction **UE** (10 Go gratuits, pas de frais de sortie) : `bulkpilot-photos` et `bulkpilot-backups` (et leurs `-staging`), un jeton par bucket ; sauvegardes avec cycle de vie et verrouillage de 30 jours | S3-compatible, liens présignés, données dans l'UE | tout S3-compatible (variables `S3_*` / `BACKUP_S3_*`) |
| Email | **Brevo** SMTP (300 emails / jour gratuits), un expéditeur vérifié | pas de domaine nécessaire | un domaine (~10 €/an) pour aligner SPF/DKIM/DMARC ; n'importe quel SMTP (`MAILER_DSN`) |
| Erreurs | SDK **Sentry** (API) et rapporteur maison (PWA), Sentry Developer en région UE ou GlitchTip auto-hébergé, nettoyage strict des données personnelles ; éteint sans DSN | | |
| Disponibilité, temps de réponse | **Uptime Kuma** sur la VM, écoutant sur la boucle locale, publié au tailnet seulement (`:10000`) : sondes HTTPS de `/api/health` et de `?scope=monitoring` ; alertes vers l'application **ntfy** | gratuit, privé | n'importe quel moniteur HTTP |
| Tâches planifiées, serveur entier | **healthchecks.io** (gratuit, 20 contrôles), **hors de la VM** : battements du worker (programmes, sauvegarde, contrôle de restauration, échecs avec leur raison) et de l'hôte (timer systemd, toutes les 5 min, seulement si la production répond) | un « interrupteur de l'homme mort » doit vivre ailleurs que ce qu'il surveille : Uptime Kuma tombe avec la VM | n'importe quel service de battements (URL `HEARTBEAT_URL_*`) |

- **Écart à la spec §144, validé par le propriétaire** : la spec cite une base PostgreSQL ; elle est **sur la VM**
  plutôt que managée (raisons ci-dessus). Le risque — la base et l'application tombent ensemble — est couvert par les
  sauvegardes chiffrées hors du serveur, la restauration testée chaque semaine et la reconstruction documentée
  (DEPLOYMENT.md, incidents).
- **Staging** sur la même VM, isolé : projet compose `bulkpilot-staging`, sa base, ses buckets, ses secrets, inscription
  ouverte, **son propre nœud Tailscale** (`bulkpilot-staging.<tailnet>.ts.net`, conteneur `tailscale`) : un autre hôte
  que la production, donc jamais les mêmes cookies (noms de cookie distincts en plus) ; plafonné à 1,5 Go et 1 CPU par
  conteneur pour ne pas affamer la production.
- **Déploiement** (`deploy/deploy.sh`, un à la fois sous un verrou) : sauvegarde chiffrée avant toute migration en
  production (`--skip-backup` délibéré seulement), `up --wait` (les migrations passent « tout ou rien » au démarrage),
  services restés debout et `/api/health`, et **retour automatique à l'image précédente** sinon. Les migrations ne font
  qu'ajouter (règle « ajouter, puis retirer », vérifiée par `make qa`) : l'ancien code lit le nouveau schéma, le
  retour en arrière n'a pas besoin de la base d'avant ; `rollback.sh --restore` la remet quand il le faut.
  `make deploy-test` rejoue tout ce chemin sur un serveur jetable en local, en CI aussi.
- **Derrière le proxy TLS**, Caddy sert en HTTP (`SERVER_NAME=:80`) et rétablit pour Symfony le schéma et l'hôte vus par
  le navigateur, de confiance seulement depuis le sous-réseau fixe du projet compose (`TRUSTED_PROXIES`) ;
  `TRUSTED_HOSTS` n'admet que l'hôte public : cookie `Secure`, contrôle de même origine et URL absolues restent justes ;
  HSTS et CSP sont envoyés comme avant.
- L'hôte se met à jour seul (sécurité, Docker et Tailscale compris), SSH n'accepte que des clés, le pare-feu est
  rejoué à chaque démarrage.

**Conséquences.**

- Coût : **0 €** tant que les limites gratuites tiennent (R2 10 Go, Brevo 300/jour, Sentry 5 000 erreurs/mois,
  healthchecks.io 20 contrôles, Tailscale 3 utilisateurs / 100 appareils, VM Always Free, GitHub public). Ce qui
  changerait la donne : plus de 10 Go de photos et de sauvegardes (R2 ~0,015 $/Go/mois), un domaine pour l'email,
  l'ouverture au public.
- Le code est public : rien de secret n'y est, et rien ne doit y entrer (secrets dans GitHub et sur le serveur). Les
  noms `*.ts.net` servis en HTTPS sont publics (transparence des certificats), sans rien de joignable derrière.
- **Risque Oracle** : une instance Always Free jugée inactive peut être récupérée, et la capacité Ampere manque parfois.
  *Pay As You Go* lève la récupération ; les sauvegardes hors du serveur et `bootstrap.sh` rendent une réinstallation
  ailleurs rapide.
- Un seul serveur : pas de haute disponibilité ; une coupure de quelques secondes à une minute à chaque déploiement.
- Une image ne part en staging qu'après toute la CI (construction multi-architecture, E2E, signature) : quelques
  minutes de plus qu'un déploiement direct, en échange de la garantie que la production tourne l'image testée.

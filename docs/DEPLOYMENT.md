# Déploiement

> Phase 10. Ce document décrit la cible ; seules les briques marquées ✅ existent aujourd'hui.

## Artefact

✅ Une **image unique** `bulkpilot-php` (target `frankenphp_prod`) contient :
FrankenPHP/Caddy, l'API Symfony (sans dépendances ni outils de dev — ni Composer ni git —, autoloader optimisé,
opcache préchargé) et la SPA Nuxt compilée (`/app/pwa`, servie en statique avec cache immuable sur `/_nuxt/*`).
Elle s'exécute en utilisateur **non-root** (`app`), avec seulement le droit d'ouvrir les ports 80/443.
Son healthcheck interroge PHP (`/api/health` sur un listener interne `127.0.0.1:2080`) : un worker bloqué ou une base
injoignable rendent le conteneur `unhealthy`.

```bash
docker compose -f docker-compose.yml build    # construit l'image de prod
make e2e-prod                                  # la teste de bout en bout
```

## Cible

```text
GitHub → CI (qa + e2e sur l'image) → registre d'images → staging → validation manuelle → production
```

| Élément | Choix prévu |
| --- | --- |
| Hébergement | VPS, Docker |
| Base | PostgreSQL managé, backups automatiques, restauration testée régulièrement |
| Fichiers | Stockage objet S3-compatible **privé**, URLs signées courte durée |
| HTTPS | Caddy (certificats automatiques), HSTS |
| Secrets | Variables serveur / secrets manager — jamais dans Git ni dans l'image |
| Monitoring | Erreurs back/front, logs JSON (stderr), temps de réponse, disponibilité, jobs en échec |

## Variables requises en production

| Variable | Rôle |
| --- | --- |
| `APP_SECRET` | secret Symfony (signature) — obligatoire : le conteneur refuse de démarrer sans |
| `APP_ENCRYPTION_KEY` | 32 octets en base64, chiffre les graines 2FA — obligatoire ; le changer invalide les authentificateurs enrôlés |
| `REGISTRATION_ENABLED` | `false` en production une fois le compte du propriétaire créé |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | connexion Google (facultatif) ; URI de redirection : `<DEFAULT_URI>/api/auth/oauth/google/callback` |
| `RATE_LIMIT_*` | seuils anti-abus (valeurs par défaut sûres dans `api/.env`) |
| `SERVER_NAME` | domaine public servi par Caddy |
| `DATABASE_URL` | PostgreSQL managé |
| `MAILER_DSN` | fournisseur email transactionnel — obligatoire (le conteneur refuse `null://`) |
| `MAILER_FROM` | expéditeur, sur un domaine authentifié (SPF/DKIM) — obligatoire |
| `DEFAULT_URI` | URL publique des liens envoyés par email — obligatoire (refus de `localhost`) |
| `FOOD_DATABASE` | `openfoodfacts` (défaut) ; `fake` uniquement pour les tests : refusé au démarrage en production sauf `ALLOW_FAKE_PROVIDERS=1` (E2E sur l'image de prod) |
| `OPEN_FOOD_FACTS_USER_AGENT` | `NomApp/Version (contact)` : Open Food Facts demande à être contacté en cas d'abus |
| `RATE_LIMIT_BARCODE_SCAN` | scans et confirmations par minute et par compte (30) ; en plus, 90 lectures / min vers Open Food Facts pour toute l'instance |
| `RATE_LIMIT_TRAINING_WRITES` | écritures Training (séances, séries, programmes, exercices) par minute et par compte (600) : une séance hors ligne se vide d'un coup au retour du réseau |
| `RATE_LIMIT_PROGRESSION_WRITES` | pesées, mensurations, objectifs et photos par minute et par compte (120) |
| `RATE_LIMIT_COACH_WRITES` | analyses relancées, décisions et réglages du Coach par minute et par compte (30) |
| `AUDIT_RETENTION_DAYS` | jours pendant lesquels l'activité du compte (journal d'audit) est gardée (365) ; purge quotidienne par le worker |
| `RATE_LIMIT_NOTIFICATION_WRITES` | vérifications à l'ouverture, lectures et réglages des notifications par minute et par compte (60) |
| `RATE_LIMIT_EXPORT_WRITES` | exports demandés par jour et par compte (5) |
| `RATE_LIMIT_PREFERENCE_WRITES` | fuseau et disposition du tableau de bord par minute et par compte (30) |
| `RUN_MIGRATIONS` | `0` sur le service `worker` : seul `php` passe les migrations au démarrage (défaut `1`) |
| `MEDIA_STORAGE` | `s3` en production (le conteneur refuse autre chose) ; `local` en développement |
| `S3_ENDPOINT` / `S3_REGION` / `S3_BUCKET` / `S3_ACCESS_KEY` / `S3_SECRET_KEY` | bucket S3-compatible **privé** des photos (ADR-0027) — bucket, clés obligatoires ; endpoint vide = Amazon S3 |
| `S3_PUBLIC_ENDPOINT` / `S3_PATH_STYLE` | l'adresse que le navigateur atteint pour les liens présignés si elle diffère de celle du serveur ; adressage par chemin (`true` pour la plupart des fournisseurs non-Amazon) |
| `BACKUP_ENABLED` | `1` en production (le conteneur refuse de démarrer sinon) : sauvegarde chaque nuit, restauration de contrôle chaque semaine (ADR-0031) ; `0` en développement |
| `BACKUP_PASSPHRASE` | chiffre les sauvegardes — obligatoire, 32 caractères au moins (`openssl rand -base64 32`) ; à garder **hors du serveur** : sans elle, les sauvegardes sont illisibles |
| `BACKUP_S3_ENDPOINT` / `BACKUP_S3_REGION` / `BACKUP_S3_BUCKET` / `BACKUP_S3_ACCESS_KEY` / `BACKUP_S3_SECRET_KEY` / `BACKUP_S3_PATH_STYLE` | bucket **privé et distinct de celui des photos** (refusé sinon), avec ses propres clés (lecture, écriture, liste, suppression) ; endpoint vide = Amazon S3 |
| `BACKUP_RETENTION_DAYS` | jours de sauvegardes gardés (30) ; la plus récente n'est jamais supprimée |
| `LOCK_DSN` | verrous (limiteurs de débit) : `flock` pour une instance, `postgresql+advisory://…` pour plusieurs |

Bucket des photos : privé (aucun accès public, aucune politique de lecture anonyme), **sans versionnage ni verrouillage
d'objet** (une suppression doit effacer), chiffrement au repos du fournisseur ; si le fournisseur garde des sauvegardes, les
limiter à 30 jours. Les envois font 16 Mo au plus (`upload_max_filesize`) : un proxy devant l'application doit l'accepter.

## Worker

✅ Un service `worker` (même image, mêmes variables, plus les secrets des sauvegardes qu'il est le seul à recevoir)
consomme tous les transports sauf `failed` (`messenger:consume --all --exclude-receivers=failed` : un nouveau programme
est consommé sans toucher à la commande) (ADR-0029) : exports, effacements de comptes et de fichiers, vérifications des notifications toutes les 15 minutes, analyse nocturne du Coach vers 3 h dans le fuseau de
chaque compte. Il démarre après `php` (qui passe les migrations), s'arrête toutes les heures ou au-delà de 192 Mo et
est relancé par `restart: unless-stopped`. Il pousse aussi les notifications une fois validées (aucun push pour l'instant).

- **Un seul worker.** L'état des programmes (dernier passage) et leur verrou sont locaux au conteneur (cache fichiers,
  `flock`) : deux workers lanceraient chacun les programmes. Les messages eux-mêmes sont sûrs à plusieurs (une
  vérification par compte au plus toutes les 5 minutes, une analyse par jour), mais ne lancer qu'un `worker`.
- **Échecs.** Un message en échec est réessayé après 10 s, 30 s puis 90 s, puis rangé dans la file `failed` :
  `bin/console messenger:failed:show` pour les voir, `messenger:failed:retry` pour les rejouer (un par un, ou `--force`
  pour tous), `messenger:failed:remove <id>` pour en retirer un. Rejouer est sûr : chaque message est idempotent. Une
  suppression de fichier en échec (stockage indisponible) garde là les clés à supprimer : la rejouer, ne pas la retirer.
  La file peut porter l'identifiant d'un compte effacé : la vider une fois les messages rejoués.
- Sans worker, l'application fonctionne : seules les vérifications à l'ouverture et l'analyse à l'ouverture du Coach
  tournent, et les notifications ne sont pas poussées (elles restent dans l'application).

## Sauvegardes

✅ Chaque nuit à 02 h 30 UTC, le worker sauvegarde la base (`pg_dump`, chiffré, envoyé au bucket `BACKUP_S3_BUCKET` sous
`db/AAAA/MM/JJ/HHMMSS.dump.enc`) puis supprime celles de plus de `BACKUP_RETENTION_DAYS` jours ; chaque dimanche à 04 h 30
UTC, il restaure la dernière dans une base jetable et la contrôle (ADR-0031). Un échec est journalisé en erreur
(`system.backup_failed`, `system.backup_verification_failed`). À la main :

```sh
docker compose exec worker bin/console app:backup:create   # une sauvegarde maintenant
docker compose exec worker bin/console app:backup:verify   # restaurer la dernière dans une base jetable et la contrôler
```

Les variables `BACKUP_*` ne vont qu'au `worker` (le conteneur `php` refuse de démarrer s'il a la phrase de passe ou la clé
secrète) : une intrusion par le serveur web ne peut pas les lire. Les clés du bucket des sauvegardes ne sont pas celles des
photos. Recommandé : une clé des sauvegardes **sans droit de suppression**, la rotation confiée à une **règle de cycle de
vie** du bucket (expiration à `BACKUP_RETENTION_DAYS` jours sous `db/`, `BACKUP_RETENTION_DAYS + 7` sous `erasures/`), et le
**verrouillage d'objet** (Object Lock, mode conformité) sur la même durée ; la suppression par l'application échoue alors
sans conséquence (elle est journalisée).

Le compte PostgreSQL de l'application doit avoir le droit `CREATEDB` (celui de l'image `postgres` l'a) pour la base de
contrôle. Le bucket : privé ; son versionnage éventuel ne doit pas garder plus de la rétention. Les données d'un compte
effacé restent dans les sauvegardes jusqu'à `BACKUP_RETENTION_DAYS` jours ; les effacements sont retenus sous
`erasures/` pour qu'une restauration ne les annule pas (étape 5 ci-dessous). La phrase de passe se range dans un gestionnaire de secrets, **pas** sur le serveur ni dans le
bucket.

### Restaurer

Procédure testée de bout en bout sur la pile E2E (image de production). Elle remplace **toute** la base par la
sauvegarde : les écritures faites depuis sont perdues.

1. Arrêter ce qui écrit dans la base : `docker compose stop php worker`.
2. Récupérer et déchiffrer la sauvegarde (la plus récente, ou `--key=db/AAAA/MM/JJ/HHMMSS.dump.enc` ; la liste :
   celle du bucket) dans un dossier de l'hôte accessible en écriture à l'utilisateur de l'image (uid 1000) :

   ```sh
   mkdir -p restore && chmod 777 restore
   docker compose run --rm --no-deps -e RUN_MIGRATIONS=0 -v "$PWD/restore:/restore" worker \
     bin/console app:backup:fetch /restore/restore.dump
   ```

3. Recréer la base et y restaurer le dump (ici `app`, l'utilisateur et la base de `DATABASE_URL`) :

   ```sh
   docker compose exec -T database dropdb -U app app
   docker compose exec -T database createdb -U app app
   docker compose exec -T database pg_restore -U app --no-owner --no-privileges --exit-on-error -d app < restore/restore.dump
   ```

   Base managée : les mêmes commandes avec `-h <hôte> -U <utilisateur>` depuis un poste qui a le client PostgreSQL.
4. Redémarrer : `docker compose up -d php worker` (les migrations plus récentes que la sauvegarde passent au démarrage).
5. **Effacer à nouveau les comptes supprimés depuis la sauvegarde** (ils reviennent avec elle), avant de rouvrir
   l'accès : `docker compose exec worker bin/console app:backup:reapply-erasures`.
6. Contrôler (connexion, dernières données attendues), puis supprimer le dump en clair : `rm -rf restore`.

## En-têtes de sécurité

La PWA de production est servie avec une `Content-Security-Policy` stricte (Caddyfile, `pwa_static`) : ses scripts et,
par leur empreinte, les scripts en ligne de sa construction (calculées au build de l'image, `frankenphp/csp-hashes.php`) ;
les images et les liens du bucket des photos (origine calculée par l'entrypoint depuis `S3_PUBLIC_ENDPOINT`, `S3_BUCKET`
et `S3_PATH_STYLE`) ; aucun cadre (`frame-ancestors 'none'`), aucun plugin, des formulaires vers elle seule.
`ALLOW_SHARED_BACKUP_KEY=1` n'existe que pour la pile E2E (une passerelle S3 à une seule clé) : jamais en production.

## Premier démarrage

1. Déployer avec `REGISTRATION_ENABLED=true`, créer le compte du propriétaire depuis la PWA, confirmer son email.
2. Repasser `REGISTRATION_ENABLED=false` et redémarrer.
3. Importer la table CIQUAL (téléchargée depuis le site de l'Anses, ~3 Mo, idempotent — à relancer à chaque nouvelle version) :
   `docker compose exec php bin/console nutrition:catalogue:import`.

## Notes d'exploitation

- Produits Open Food Facts : le cache (`cache.app`, 1 jour par produit) et le budget de 90 lectures / min sont **par instance** ; avec
  plusieurs instances, partager le cache (Redis) et les limiteurs (`LOCK_DSN` + stockage partagé), sinon le budget est multiplié.

- Chaque requête authentifiée ouvre sa propre connexion PostgreSQL pour la session (en plus de Doctrine) :
  prévoir ~2 × threads worker connexions, ou un pooler (PgBouncer) devant une base managée.
- Les emails partent après la réponse ; une panne SMTP est journalisée (`Email could not be sent`) sans échouer la requête.

## Restant pour la phase 10

- Migrations dans un job de release unique plutôt qu'à chaque démarrage (dès qu'il y a plusieurs répliques).
- Image : réduire la taille (~780 Mo), images de base épinglées par digest, SBOM, scan Trivy, push par digest sur GHCR.
- Stockage des limiteurs de débit partagé entre répliques (base ou Redis) au lieu du cache fichier par conteneur,
  et `LOCK_DSN` partagé ; `trusted_proxies` indispensable derrière un load balancer (sinon tous les clients partagent son IP).
- Rétention des logs d'audit (ils contiennent des identifiants utilisateur).
- Supervision des sauvegardes : alerter sur `system.backup_failed` / `system.backup_verification_failed` (et sur
  l'absence de `system.backup_created` depuis 36 h).
- Mot de passe PostgreSQL obligatoire (`${POSTGRES_PASSWORD:?}`) dans l'override de production, `trusted_proxies` si un load balancer est placé devant.

Les environnements `local`, `staging` et `production` ont des données strictement séparées ;
aucune donnée de production n'est copiée vers un autre environnement. Les fixtures de démonstration (à venir avec les premières données métier) ne seront jamais chargées en production
(bundle limité à `dev`/`test`).

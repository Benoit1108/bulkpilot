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
| `LOCK_DSN` | verrous (limiteurs de débit) : `flock` pour une instance, `postgresql+advisory://…` pour plusieurs |

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
- Mot de passe PostgreSQL obligatoire (`${POSTGRES_PASSWORD:?}`) dans l'override de production, `trusted_proxies` si un load balancer est placé devant.

Les environnements `local`, `staging` et `production` ont des données strictement séparées ;
aucune donnée de production n'est copiée vers un autre environnement. Les fixtures de démonstration (à venir avec les premières données métier) ne seront jamais chargées en production
(bundle limité à `dev`/`test`).

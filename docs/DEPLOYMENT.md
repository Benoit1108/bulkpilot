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
| `SERVER_NAME` | domaine public servi par Caddy |
| `DATABASE_URL` | PostgreSQL managé |
| `MAILER_DSN` | fournisseur email transactionnel |
| `DEFAULT_URI` | URL publique utilisée pour les liens générés hors requête (emails) |

## Restant pour la phase 10

- Migrations dans un job de release unique plutôt qu'à chaque démarrage (dès qu'il y a plusieurs répliques).
- Image : réduire la taille (~780 Mo), images de base épinglées par digest, SBOM, scan Trivy, push par digest sur GHCR.
- Mot de passe PostgreSQL obligatoire (`${POSTGRES_PASSWORD:?}`) dans l'override de production, `trusted_proxies` si un load balancer est placé devant.

Les environnements `local`, `staging` et `production` ont des données strictement séparées ;
aucune donnée de production n'est copiée vers un autre environnement. Les fixtures de démonstration (à venir avec les premières données métier) ne seront jamais chargées en production
(bundle limité à `dev`/`test`).

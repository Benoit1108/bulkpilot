# Supervision

Ce que la production surveille, comment le brancher, et quoi faire quand une alerte arrive. Le déploiement
(serveur, Tailscale, comptes à créer) est décrit dans [DEPLOYMENT.md](DEPLOYMENT.md) ; les choix et leurs raisons
dans l'ADR-0033. Tout est gratuit au départ et se coupe en laissant une variable vide.

## Ce qui est surveillé

| Quoi | Comment | Où ça se voit |
| --- | --- | --- |
| L'API répond, la base aussi | `GET /api/health` : `200`, ou `503` + `status: degraded` si la base est injoignable | sonde du conteneur, et moniteur HTTP « disponibilité » d'Uptime Kuma |
| Temps de réponse | le moniteur « disponibilité » mesure chaque appel | graphe et alerte de lenteur dans Uptime Kuma |
| Le worker tourne ses planifications | un **battement** au démarrage du worker puis toutes les 15 min (planification `monitoring`), gardé en base (`system_heartbeats`) et envoyé à healthchecks.io | `GET /api/health?scope=monitoring` → `monitors.worker` ; contrôle healthchecks.io « scheduler » |
| Le worker vit | à chaque tour de boucle, il touche `var/worker.alive` ; la sonde de son conteneur lit l'âge de ce fichier | `docker compose ps` (un worker planté ou bloqué devient `unhealthy`) |
| Les sauvegardes ont lieu | un battement après chaque sauvegarde réussie, un échec avec sa raison sinon | `monitors.backup` (en retard après 26 h) ; contrôle « backup » |
| La restauration de test passe | idem, chaque semaine | `monitors.backup_verify` (en retard après 8 jours) ; contrôle « backup verify » |
| Disque | espace libre de `var/` (cache, journaux, fichiers temporaires des exports et sauvegardes) | `monitors.disk` : `low` sous 1 Gio ou sous 10 % |
| Le serveur entier | healthchecks.io, hors du serveur, alerte quand les battements **s'arrêtent** : VM éteinte, réseau coupé, Uptime Kuma tombé avec elle | contrôles healthchecks.io (DEPLOYMENT.md) |
| Erreurs de l'API | SDK Sentry (Sentry ou GlitchTip), en production seulement, si `SENTRY_DSN` est rempli : exceptions des requêtes, du worker (messages en échec définitif) et des commandes | projet Sentry/GlitchTip « api » |
| Erreurs de la PWA | rapporteur maison (quelques Ko, chargé seulement si `PWA_SENTRY_DSN` est rempli), même format que Sentry | projet Sentry/GlitchTip « pwa » |
| Journaux | JSON sur la sortie d'erreur de chaque conteneur, rotation Docker (`json-file`) | `make server-logs` (DEPLOYMENT.md) |

`/api/health` (sans paramètre) reste une **sonde de disponibilité** : la base et rien d'autre, pour qu'un worker en
retard ne fasse pas tomber le conteneur web. `?scope=monitoring` ajoute les moniteurs et passe à `503` dès que l'un
d'eux n'est ni `ok`, ni `pending`, ni `disabled` :

```json
{"status":"ok","checks":{"database":"up"},"monitors":{"worker":"ok","backup":"ok","backup_verify":"pending","disk":"ok"}}
```

Sans `scope`, `monitors` vaut `null`.

- `unknown` : jamais vu depuis que la base existe. Juste après un premier déploiement, le worker bat dès son
  démarrage ; la sauvegarde, la première nuit — lancer `app:backup:create` une fois à la main l'enregistre tout de
  suite.
- `pending` : pas encore dû. La restauration de test est hebdomadaire ; tant que la première sauvegarde a moins de
  8 jours, son absence n'est pas une alerte (passé ce délai, `unknown`).
- `disabled` : sauvegardes non attendues sur ce conteneur. L'API ne sauvegarde pas elle-même (c'est le worker) : elle
  sait que des sauvegardes sont attendues par `BACKUPS_MONITORED=1`, posé sur le service `php` des environnements
  qui sauvegardent (`0` en développement).

`/api/health` et `/api/client-config` sont publics : sans risque tant que l'application ne s'ouvre qu'au tailnet.
**Avant toute exposition publique** (Funnel, tunnel), `?scope=monitoring` doit être réservé (jeton ou réseau) : il dit
l'état des sauvegardes et du disque, et `/api/client-config` la version déployée.

## Uptime Kuma

Uptime Kuma tourne sur le serveur, joignable seulement par le tailnet (DEPLOYMENT.md). Il suit la disponibilité et le
temps de réponse ; les tâches planifiées sont suivies de l'extérieur, par healthchecks.io (ci-dessous). Moniteurs :

| Moniteur | Type | Réglage |
| --- | --- | --- |
| BulkPilot — disponibilité | HTTP(s) | URL `https://<serveur>.<tailnet>.ts.net/api/health`, code attendu 200, intervalle 60 s, 3 essais avant alerte |
| BulkPilot — tâches et disque | HTTP(s) - Json Query | même hôte, `/api/health?scope=monitoring`, expression `status`, valeur attendue `ok`, intervalle 5 min, 2 essais |
| BulkPilot — staging | HTTP(s) | `http://127.0.0.1:8081/api/health` (le port local de staging : la politique Tailscale ne laisse rien sortir du serveur vers son nœud), intervalle 5 min |

Séparer les deux premiers évite qu'une sauvegarde en retard marque la production « hors service ». Uptime Kuma passe
par le nom `ts.net` comme l'iPhone : il voit aussi le certificat (cocher *Certificate Expiry Notification*).
Notifications : ntfy (ci-dessous), appliquée à tous les moniteurs.

## healthchecks.io

Gratuit jusqu'à 20 contrôles, **hors du serveur** : il alerte quand un battement attendu n'arrive pas, donc aussi
quand la VM entière est tombée (ce qu'Uptime Kuma, qui tourne dessus, ne peut pas dire). Contrôles à créer, chacun avec
son URL de ping (`https://hc-ping.com/<uuid>`) :

| Contrôle | Période | Délai de grâce | Variable |
| --- | --- | --- | --- |
| scheduler | 15 min | 25 min | `HEARTBEAT_URL_SCHEDULER` |
| backup | 1 jour | 2 h | `HEARTBEAT_URL_BACKUP` |
| backup verify | 7 jours | 1 jour | `HEARTBEAT_URL_BACKUP_VERIFY` |
| server | 5 min | 10 min | `HEALTHCHECKS_HOST_URL` (`/opt/bulkpilot/env/server.env`) |

Un succès est un `GET` sur l'URL ; un échec, un `POST` sur `<url>/fail` avec la raison en corps (alerte immédiate,
sans attendre la période). Un contrôle injoignable ne fait jamais échouer le travail surveillé : l'appel dure 3 s au
plus, et un échec — y compris une réponse d'erreur, par exemple 404 pour une URL fausse — est seulement journalisé
(`system.heartbeat_not_sent`). Un battement que la base ne peut pas écrire est journalisé lui aussi
(`system.heartbeat_not_recorded`) sans faire échouer une sauvegarde réussie. Le contrôle du serveur entier, appelé par
l'hôte lui-même (timer systemd `bulkpilot-heartbeat`), n'est envoyé que si la production répond `/api/health` sur sa
boucle locale ; sinon il signale l'échec (`/fail`). Les trois premiers concernent la production : staging laisse ses
`HEARTBEAT_URL_*` vides (ou des contrôles à part).

### Notifications sur l'iPhone

L'application **ntfy** (gratuite) : créer un sujet privé au nom imprévisible (`bulkpilot-<aléatoire>`), l'ajouter dans
l'app, puis le brancher à la fois dans Uptime Kuma (*Paramètres → Notifications → ntfy*, serveur `https://ntfy.sh`,
priorité haute, appliquée à tous les moniteurs) et dans healthchecks.io (*Integrations → ntfy*). Un e-mail peut servir
de second canal.

## Signalement des erreurs (Sentry ou GlitchTip)

- **Sentry** : offre « Developer » gratuite (5 000 erreurs/mois). Créer deux projets, *PHP / Symfony* et *Browser
  JavaScript* ; leurs DSN vont dans `SENTRY_DSN` (API) et `PWA_SENTRY_DSN` (PWA). **GlitchTip** (libre, hébergeable)
  accepte les mêmes DSN si l'on préfère garder les erreurs chez soi.
- `SENTRY_ENVIRONMENT` (`production`, `staging`) et `APP_RELEASE` (le commit de l'image, posé à la construction)
  accompagnent chaque erreur côté API. Pas de suivi de performance (le traçage est coupé).
- La PWA lit son DSN par `GET /api/client-config` au démarrage (public, en cache 5 min) : le même build sert la
  staging et la production. Sa version (`release`) est celle de **son propre build** (`NUXT_PUBLIC_RELEASE`, posé à la
  construction de l'image) — une application installée restée ouverte après un déploiement exécute encore l'ancien
  code ; celle de l'API l'accompagne en étiquette (`api_release`). L'entrypoint ajoute l'hôte du DSN à `connect-src`.

### Ce qui ne part jamais

C'est vérifié par des tests (`SentryEventScrubberTest`, `pwa/test/unit/monitoring.test.ts`) :

- ni le corps des requêtes, ni les cookies, ni les en-têtes (sauf `Content-Type` et `Accept`), ni les paramètres de
  requête (`?…` retiré de chaque URL, y compris dans les messages), ni l'adresse IP, ni l'environnement du serveur ;
- ni l'e-mail ni le nom : l'utilisateur est son **identifiant opaque** (UUID) seul côté API, et rien côté PWA ;
- ni les e-mails ni les jetons longs dans les messages, extras et étiquettes (remplacés par `[email]`, `[token]`) ;
  les UUID restent : ce sont des identifiants opaques, et le seul moyen de retrouver l'enregistrement concerné ;
- ni les valeurs qu'une erreur SQL cite (`Key (…)=(…)`, `Failing row contains (…)`, paramètres de requête) : un
  poids ou une note est une donnée de santé ; ni les variables locales des piles d'appels, ni celles des exceptions
  chaînées ;
- ni les paramètres SQL ni les données des « fils d'Ariane » (le message seul, nettoyé) ; ni la console côté PWA ;
- ni les réponses attendues : 4xx (404, 409, 422, 429…), connexion refusée ou session expirée (401/403 du pare-feu),
  requête illisible (400), règles métier — ce ne sont pas des erreurs et elles ne partent pas ; un service externe
  indisponible (503) et les erreurs fatales (mémoire épuisée) partent. **Exception** : dans le worker, une tâche qui
  échoue définitivement part quelle qu'en soit la cause (même une règle métier) — personne n'est là pour la voir ;
- côté PWA, ni les erreurs d'API que l'application gère (introuvable, conflit, validation, hors ligne, session
  expirée) : seules les erreurs serveur partent, réduites à leur statut et leur titre (`HTTP 500 Internal Server
  Error`, jamais le détail, qui peut citer une saisie), avec l'identifiant de requête (`request_id`) qui retrouve
  l'erreur côté API.

Le SDK Sentry n'est chargé qu'en production ; côté PWA, rien n'est chargé tant que `/api/client-config` ne donne pas
de DSN, et le rapporteur maison n'envoie que le type, le message nettoyé (1 000 caractères au plus), la pile
(fichiers sans requête), le chemin de la page, le point de capture (`onerror`, `onunhandledrejection`, `vue`,
`nuxt`), l'environnement et la version — chaque erreur une fois, dix rapports au plus par chargement de page.
Ouverte hors ligne, la PWA garde ses erreurs et réessaie de lire sa configuration au retour du réseau.

## Journaux

Chaque conteneur écrit du JSON sur sa sortie d'erreur (Monolog `php://stderr`, Caddy) ; la rotation Docker
(`json-file`, taille et nombre de fichiers bornés) évite de remplir le disque. Les identifiants de session, cookies,
jetons et codes ne sont jamais journalisés (filtres Caddy, ADR-0008). Le canal `audit` garde les actions importantes
pour l'opérateur (ADR-0030) ; il suit la même rotation.

Lire : `make server-logs` (tous les services) ou `make server-logs SERVICE=worker` (`ENV=staging` pour staging), et filtrer par niveau
(`"level_name":"ERROR"`) ou par message (`system.backup_failed`).

## Quand une alerte arrive

| Alerte | Première chose à regarder | Ensuite |
| --- | --- | --- |
| Production en `503`, `checks.database: down` | `make server-logs SERVICE=database` | redémarrer le service, vérifier le disque ; restaurer depuis la dernière sauvegarde si la base est perdue (DEPLOYMENT.md, « Restaurer ») |
| healthchecks.io : tous les contrôles en retard en même temps | la VM (console Oracle), Tailscale | le serveur entier est injoignable ou arrêté : le redémarrer depuis la console ; vérifier qu'Oracle ne l'a pas récupéré (DEPLOYMENT.md) |
| `monitors.worker: late`, contrôle « scheduler » en retard ou worker `unhealthy` | `make server-logs SERVICE=worker` | redémarrer le worker ; si des messages restent en échec, `messenger:failed:show` puis `messenger:failed:retry` (DEPLOYMENT.md) |
| Contrôle « backup » en échec (avec la raison) | la raison dans healthchecks.io, puis les journaux du worker (`system.backup_failed`) | relancer `bin/console app:backup:create` une fois la cause réglée (identifiants R2, quota, disque) |
| `monitors.backup_verify` en retard ou contrôle en échec | `system.backup_verification_failed` | **ne pas attendre** : une sauvegarde qu'on ne sait pas restaurer n'en est pas une ; corriger puis `app:backup:verify` |
| `system.heartbeat_not_sent` dans les journaux | l'URL du contrôle (`HEARTBEAT_URL_*`) | une URL fausse répond 404 : la recopier depuis healthchecks.io |
| `monitors.disk: low` | `docker system df`, taille de `var/` | purger les vieilles images (`docker image prune`), vérifier la rotation des journaux |
| Pic d'erreurs dans Sentry | la version (`release`) et l'environnement de l'erreur | revenir à l'image précédente si elle vient d'un déploiement (DEPLOYMENT.md, « Revenir en arrière ») |
| Production lente (temps de réponse) | Uptime Kuma (depuis quand), charge du serveur | `make perf` reproduit la charge ; regarder les requêtes lentes dans les journaux PostgreSQL |

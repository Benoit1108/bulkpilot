# Développement

## Prérequis

Docker (Compose ≥ 2.24) et `make`. Tout le reste (PHP 8.5, Composer, Node 24, pnpm 12) tourne en conteneur.

## Stack de dev

```bash
make up          # https://localhost (PWA), /api/docs, /_profiler ; Mailpit sur :8025
make logs        # logs de tous les services
make down
```

- `api/` est monté dans le conteneur `php` ; FrankenPHP redémarre le worker à chaque modification de `src/` ou `config/`.
  **Après un `composer require/remove`, lancer `make restart`** (`vendor/` n'est pas surveillé).
- `pwa/` est monté dans le conteneur `pwa` (exécuté avec votre uid) ; Vite fait le HMR à travers Caddy (même origine, wss).
- `node_modules` du conteneur vivent dans un volume nommé (binaires Linux/musl).
- PostgreSQL (`:5432`) et Mailpit (`:8025`) n'écoutent que sur `127.0.0.1`.

## Commandes courantes

```bash
make sh-php                                  # shell PHP
make composer ARGS="require vendor/pkg" && make restart   # exécuté avec ton uid : pas de fichiers root
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec pwa pnpm add pkg

make db-diff && make db-migrate              # après un changement de mapping
make ciqual                                  # importe / rafraîchit la table CIQUAL (téléchargement Anses)
FOOD_DATABASE=openfoodfacts make up          # scanner de vrais produits (défaut en dev : base factice)
make openapi                                 # après un changement de ressource API
make php-cs-fix                              # formatage PHP
docker compose … exec pwa pnpm lint:fix      # formatage front
```

## Ajouter un use case (backend)

1. `Domain` : modèle, règles, événements, interface de repository — **sans** Doctrine/Symfony.
2. `Application` : command à l'impératif (`RecordWeight`) + `RecordWeightHandler implements CommandHandler`
   (ou query `GetFoo` + `GetFooHandler implements QueryHandler`). Pas de suffixe `Command`/`Query`.
3. `Infrastructure` : adapter Doctrine + mapping XML ; déclarer le mapping du contexte dans `config/packages/doctrine.yaml`.
4. `Presentation` : ressource API Platform + processor/provider qui appelle le bus ; ajouter le dossier
   `<Context>/Presentation/Api` à `api_platform.mapping.paths`. Les listes se typent en PHPDoc (`@param list<Meal>`) :
   le schéma OpenAPI (et donc les types TypeScript) en dépend.
5. Tests : unitaires sur le domaine/handler, API sur l'endpoint, puis `make qa`.

## Débogage

- Xdebug : `XDEBUG_MODE=debug make up` (IDE à l’écoute sur le port 9003 de la machine hôte).
- Profiler Symfony : lien `x-debug-token-link` dans les en-têtes de chaque réponse API.
- Nuxt DevTools : bouton flottant en bas de l'écran (dev uniquement).

## HTTPS sur téléphone (réseau local)

`SERVER_NAME="localhost, https://php, 192.168.x.y" make up` (garder `https://php`, utilisé par `make e2e`),
puis `make cert` et installer `caddy-root.crt` sur le téléphone. Publier le port 443 sur le réseau local expose l'app :
à faire uniquement sur un réseau de confiance.

# Architecture

## Vue d'ensemble

```text
Navigateur / PWA installée
        │  HTTPS, une seule origine (cookies de session first-party)
        ▼
Caddy (FrankenPHP)
 ├── /api/*, /_profiler, /bundles  →  Symfony (worker mode)
 └── tout le reste                 →  SPA Nuxt (fichiers statiques en prod, serveur Vite en dev)
        │
        ├── PostgreSQL        données métier
        ├── Stockage objet    photos (local en dev, S3 privé en prod — phase 5/8)
        └── Providers         IA, base alimentaire, email — derrière des interfaces, Fake en local
```

Le frontend ne contient **aucune règle métier** : il affiche, navigue et appelle l'API.
Symfony calcule, décide, valide et orchestre. L'IA (phase 6) reformule des analyses déjà calculées.

## Backend : bounded contexts × couches hexagonales

```text
api/src/
├── Shared/          noyau partagé minimal (identifiants, événements, bus, périodes, ports `Photos`, `Archives`,
│                    `PersonalData`, `ErasedAccounts`, `AuditTrail`, `Accounts`, lectures du Coach et des notifications)
├── Identity/        comptes, profils, sessions, 2FA
├── Nutrition/       aliments, recettes, repas, journal, objectifs
├── Training/        exercices, programmes versionnés, séances, séries, PR
├── Progression/     pesées, mensurations, photos, objectifs de poids
├── Coach/           moteur de règles, analyses, recommandations
├── Notification/    règles, notifications dans l'app, catégories, horaires silencieux (ADR-0029)
├── Media/           photos privées : réencodage (GD), stockage local ou S3, liens signés (port Shared `Photos`, ADR-0027)
├── Audit/           journal des actions importantes, « Activité du compte », pseudonymisation (ADR-0030)
├── Export/          exports des données personnelles (la suppression du compte est dans Identity, ADR-0030)
└── System/          préoccupations opérationnelles (santé du service, sauvegardes, supervision) — pas un contexte métier (ADR-0013, ADR-0031, docs/MONITORING.md)
```

Chaque contexte suit les mêmes couches :

| Couche | Contient | Peut dépendre de |
| --- | --- | --- |
| `Domain` | Entités, value objects, agrégats, événements, interfaces de repositories, règles | `Psr\Clock` uniquement |
| `Application` | Commands, queries, handlers (use cases), DTO, ports | `Domain`, `Psr\Clock`, `Psr\Log` |
| `Infrastructure` | Doctrine, adapters (IA, email, stockage, Open Food Facts), bus Messenger | tout sauf `Presentation` |
| `Presentation` | Ressources API Platform, providers/processors, DTO HTTP | `Application`, `Domain`, framework |

Ces règles sont **vérifiées en CI** par Deptrac :

- `deptrac.yaml` — règles de couches. Une couche `Vendor` attrape **toute** classe tierce (framework, SDK, librairie) :
  le domaine et l'application ne peuvent en utiliser aucune, et `--fail-on-uncovered` rejette toute classe hors couche ;
- `deptrac.contexts.yaml` — isolation des contextes : un contexte ne dépend que de `Shared`.
  Toute dépendance entre contextes passe par un événement ou une query et nécessite un ADR.

### Flux d'une requête

```text
HTTP → API Platform (ressource Presentation, validation, sérialisation)
     → Provider / Processor → QueryBus / CommandBus
     → Handler (Application) → Domaine → Repository (interface)
     → Adapter Doctrine (Infrastructure) → PostgreSQL
```

Exemple réel : `GET /api/health` — `System/Presentation/Api/Health/HealthProvider` → `QueryBus::ask(GetHealth)`
→ `GetHealthHandler` → ports `HealthCheck` → `DatabaseHealthCheck` (DBAL). Chaque contexte déclare son dossier
`Presentation/Api` dans `config/packages/api_platform.yaml` (API Platform ne scanne pas tout `src/`).

Conventions de nommage : commands à l'impératif (`RecordWeight`), queries en `Get…`/`List…` (`GetHealth`),
handlers suffixés `Handler`, événements au passé (`WeightRecorded`). Pas de suffixe `Command`/`Query`.

### Bus

| Bus | Rôle | Middleware |
| --- | --- | --- |
| `command.bus` | écritures, un handler | ping/close connexion, **transaction Doctrine** |
| `query.bus` | lectures, un handler, retourne un résultat | — |
| `event.bus` | événements de domaine, 0..n abonnés | livrés **après** le commit de la commande, **chaque livraison dans sa propre transaction** |
| `job.bus` | travaux longs (`Job` / `JobHandler` : un export, une sauvegarde) | ping/close connexion, **pas de transaction** : le travail écrit par des commandes courtes |

Les handlers ne portent aucun attribut framework : ils implémentent `CommandHandler`, `QueryHandler`
ou `DomainEventSubscriber`, et `Kernel::build()` les rattache au bon bus (y compris les services de test).
La validation des entrées se fait sur les DTO de `Presentation` (API Platform → 422), pas sur les bus.

Sémantique des événements (ADR-0014) : un handler publie ses événements via `EventBus::publish()` ; ils sont
différés jusqu'au succès de la commande et abandonnés si elle échoue. Si un abonné synchrone échoue **après** le commit,
la commande reste un succès (sinon le client réessaierait et appliquerait l'écriture deux fois) : l'échec est journalisé
en `error`. Une réaction qui ne doit pas être perdue est routée vers le transport `async` (retries + file `failed`).
Ces comportements sont couverts par `tests/Integration/Shared/Infrastructure/Bus/BusWiringTest.php`.

Le transport `async` (Doctrine, table `messenger_messages`) porte les tâches différables (les photos sont traitées à
l'envoi, ADR-0027). Y sont routés, un message par compte, les vérifications des notifications (`CheckAccount`), les
analyses nocturnes du Coach (`AnalyseAccount`) et les effacements de comptes (`EraseAccount`), les exports
(`GenerateExport`, un travail), et les réactions qui ne doivent pas se perdre : `NotificationCreated` (le push),
`MediaFileErased` et `ExportExpired` (les fichiers à supprimer), `AccountErased` (retenu à côté des sauvegardes). Six
programmes Symfony Scheduler (`notifications`, `coach`, `identity`, `export`, `audit`, `backups`, ADR-0029 à 0031),
rangés en `Presentation/Scheduler` (ils pilotent l'application comme une commande console), les envoient. Le service
`worker` du compose (même image que `php`, sans serveur web, **un seul**) consomme tout :
`messenger:consume --all --exclude-receivers=failed`. En dev, `docker compose restart worker` après avoir
changé un handler.

### Authentification

Symfony Security ne voit qu'un `SecurityUser` (Infrastructure d'Identity) : identifiant utilisateur + appareil.
Les identifiants sont vérifiés par le use case `CheckCredentials` ; la Presentation ouvre alors une `UserSession`
puis appelle le port `SessionAuthenticator` (implémenté avec `Security::login()`). À chaque requête, le provider
recharge l'appareil : révoqué ⇒ requête déconnectée ⇒ 401. Les sessions HTTP vivent en base (`PdoSessionHandler`).

### Emails

Les emails partent **après la réponse** (`DeferredMailer`, sur `kernel.terminate`) : le temps de réponse ne révèle pas si
un email a été envoyé (pas d'énumération des comptes), et une panne SMTP n'échoue pas la requête (erreur journalisée,
lien redemandable). Le lien lui-même est créé dans la transaction de la commande.

### Concurrence

Les agrégats qui portent des valeurs à usage unique (`User` : pas TOTP, codes de secours ; `OneTimeToken`) ont une
colonne `version` (verrou optimiste). Une course détectée par la base (version, contrainte d'unicité) devient
`ConcurrentUpdate` (409) dans le bus de commandes, jamais une 500.

### Persistance

- Mapping Doctrine **XML** dans `<Context>/Infrastructure/Persistence/Doctrine/Mapping` : le domaine reste pur (ADR-0009).
- Identifiants UUID v7 (ordonnés dans le temps), encapsulés dans des value objects héritant de `Shared\Domain\Identifier\Uuid`,
  stockés en colonnes `uuid` natives via des types Doctrine dédiés (`UuidType`). Dates en `timestamptz`.
- Les agrégats se référencent par identifiant, sans clé étrangère ORM entre agrégats.
- Migrations versionnées dans `api/migrations`, exécutées au démarrage du conteneur.

### FrankenPHP worker mode

Le kernel reste en mémoire entre les requêtes. Règles :

- aucun état lié à une requête ou à un utilisateur dans une propriété de service (services `final readonly` par défaut) ;
- un service qui doit garder un cache mémoire implémente `ResetInterface` ;
- l'EntityManager est réinitialisé par Symfony entre les requêtes (registre Doctrine tagué `kernel.reset`).

En dev, le worker redémarre quand `src/` ou `config/` changent ; après un changement de `vendor/`, `make restart`.
Le healthcheck du conteneur interroge PHP (`/api/health` sur un listener interne), un worker bloqué est donc détecté.
La supervision externe (Uptime Kuma) lit `/api/health?scope=monitoring`, qui ajoute le battement du planificateur, l'âge
des sauvegardes et l'espace disque sans toucher à la sonde de disponibilité (docs/MONITORING.md).
Vérifié par `tests/Api/Identity/WorkerModeTest.php` : deux utilisateurs alternent 20 fois sur un même kernel,
avec seulement le `services_resetter` entre deux requêtes (ce que fait la boucle du worker) ; aucun jeton ne survit au reset.
Un service qui garde un état par requête l'annonce et implémente `ResetInterface` (ex. `DeferredMailer`).

## Frontend

```text
pwa/app/
├── pages/          routes fines : sections, pages publiques d'identité (connexion…), reglages/{index,profil,compte}
├── layouts/        default (navigation, en-tête, bandeau de connectivité) · auth (pages publiques) · focus (séance, plein écran)
├── middleware/     auth.global.ts : pages privées → connexion (règles pures dans features/identity/access.ts)
├── components/
│   ├── ui/         design system (UiButton, UiLinkButton, UiCard, UiInput, UiSelect, UiAlert, UiEmptyState…), classes.ts
│   └── shell/      ShellNav, ShellConnectivityBanner, ShellUpdatePrompt, ShellBackLink
├── features/       code par fonctionnalité : identity/, nutrition/, training/ (séance, file d'envoi, plans gardés), progression/ (poids, mensurations, photos, analyses), shell/, system/
├── composables/    useApi, useAccount, useSubmission, useUnsavedChanges, useWakeLock, useTheme, usePwaUpdate, useEventListener
├── services/api/   contrat typé (contract.ts), client unique (client.ts), erreurs et messages par code
├── services/offline/ IndexedDB (store.ts, mémoire en repli) et compte propriétaire de l'appareil (owner.ts)
├── services/calendar/ jours locaux de l'utilisateur et leurs formats
├── services/media/ réduction d'une photo avant l'envoi
├── services/text/  recherche sans accents, nombres à la française
├── plugins/        api (client + gestion du 401), messages (enregistrement par fonctionnalité), theme
├── error.vue       page d'erreur en français, sans détail technique
└── types/          api.d.ts généré depuis l'OpenAPI (ne pas éditer)
```

- **SPA** (`ssr: false`) : le shell est précaché par le service worker et démarre hors ligne (ADR-0005).
- **Client API unique** (`services/api/client.ts`) : cookie de session same-origin, JSON, erreurs normalisées
  en `ApiError` (message utilisateur / message technique / `traceId` = en-tête `X-Request-Id` posé par Caddy).
  Les lectures sont réessayées une fois (coupure réseau, 500/502/503/504), jamais les écritures.
  `asApiError()` récupère l'`ApiError` d'origine derrière l'enveloppe de `useAsyncData`.
- **Types générés** : `make openapi` exporte la spec (et `openapi/error-codes.json`) et régénère `app/types/api.d.ts` ;
  `make openapi-check` (CI) échoue si l'un est périmé. Un test Vitest exige un message français pour chaque code d'erreur.
- **Session perdue** : seul un appareil *qui était connecté* est renvoyé vers la connexion (et ses données en cache vidées) ;
  le 401 attendu d'un visiteur ne déclenche rien.
- **Navigation** : barre basse sur téléphone ; rail vertical dès 48rem ; barre latérale libellée dès 75rem.
  Les Réglages sont séparés des cinq sections (en-tête sur téléphone, bas du rail sinon).
- **Design system** : tokens dans le thème Tailwind (`assets/css/tokens.css`), thème sombre par défaut (clair et « système »
  dans les Réglages : seules les couleurs changent), accent violet, `accent-fill` pour les surfaces portant du texte blanc
  (contraste AA), cibles tactiles ≥ 44 px (`min-h-tap`, `size-tap`), état actif de navigation non porté par la seule couleur.
- **Styles** (ADR-0025) : Tailwind v4, sans son preflight (la base est celle de `main.css`). Règles :
  - uniquement des classes utilitaires dans les templates, pas de bloc `<style>` ; exception : ce que les utilitaires ne savent
    pas dire proprement (animation propre, pseudo-élément d'un composant tiers), dans un `<style scoped>` court et commenté ;
  - couleurs, rayons, tailles de texte **du thème uniquement** (`bg-surface`, `text-muted`, `border-line`, `rounded-md`,
    `text-sm`…), jamais de valeur brute ; espacements sur l'échelle (`gap-4` = 1 rem) ;
  - les variantes d'un composant de base vivent dans le composant (`UiButton`, `UiLinkButton`, `UiLinkRow`) ou dans
    `components/ui/classes.ts` (boutons, `CHIP`, `TEXT_LINK`, `LIST_ROW`, champs), écrites en entier (Tailwind lit les sources :
    une liste de classes peut être jointe à une autre, un nom de classe n'est jamais fabriqué par morceaux) ;
  - zones sûres de l'écran (encoche, barre d'accueil) par les utilitaires de `main.css` : `pt-safe-4`, `pb-safe-*`, `px-gutter`,
    `pb-nav`, `bottom-above-nav`… plutôt que des `calc(env(...))` recopiés ;
  - pas de `focus:outline-none` à côté d'un `focus-visible:outline-*` sur le même élément : en v4 le premier annule le second
    (l'anneau global de `main.css` suffit) ;
  - pas de classe « crochet » pour les tests : rôle et nom accessibles d'abord, `data-testid` sinon ;
  - pas de `>` dans un attribut de template (le garde-fou typographique lit le template) : sortir l'expression dans le script ;
  - ESLint (`better-tailwindcss`) refuse une classe inconnue ou en conflit et impose l'ordre des classes (`make pwa-lint-fix`).
- **Graphiques** (ADR-0026) : `UiLineChart` (SVG, géométrie pure dans `components/ui/chart.ts`) — titre, résumé en une phrase,
  lecture au pointeur et aux flèches (annoncée), tableau des valeurs, lignes distinguées par leur tracé ; `xDomain` aligne deux graphiques.
- **Formulaires** : un formulaire d'édition appelle `useUnsavedChanges(body)` ; quitter la page (ou l'application) après
  une modification non enregistrée demande confirmation.
- **Taille des composants** : 250 lignes au plus par fichier `.vue` (ESLint `max-lines`, lignes vides et commentaires exclus) ;
  au-delà, la logique part dans un composable et les morceaux d'écran dans des composants.
- **Thème** : `public/theme-init.js` l'applique avant le premier rendu ; `features/shell/theme.ts` centralise les valeurs.
- **Hors ligne** (ADR-0024) : la séance en cours et sa file d'envoi vivent dans IndexedDB, écrites ensemble ; le plugin
  `offline.client.ts` lie l'appareil au compte connecté (données d'un autre compte effacées), puis vide la file.
- **Connectivité** : `ShellConnectivityBanner` n'apparaît que si le serveur est injoignable ou dégradé ; le détail est dans Réglages › À propos.
- **Mises à jour** : le service worker ne s'active qu'après validation (« Mettre à jour » / « Plus tard ») ; vérification horaire.

## Production

Une VM arm64 (Oracle Cloud Always Free) privée derrière Tailscale, sans port ouvert sur Internet (ADR-0033) :
`tailscale serve` termine le HTTPS et relaie vers Caddy en HTTP sur la boucle locale (`SERVER_NAME=:80`,
`TRUSTED_PROXIES`), un projet compose par environnement (`deploy/compose.yaml` : php, worker, PostgreSQL ; staging a
en plus son propre nœud Tailscale), Uptime Kuma à part (`deploy/ops.compose.yaml`). L'image de chaque commit est
construite une fois par la CI (amd64 et arm64, GHCR), testée sur ce digest puis signée ; le serveur la tire par digest,
vérifie la signature, la lance en staging puis promeut ce même digest en production par `deploy/deploy.sh` (sauvegarde
avant, retour automatique à l'image précédente en cas d'échec). La CI n'atteint le serveur que par une commande
filtrée (`deploy/gate.sh`). Mode d'emploi : docs/DEPLOYMENT.md.

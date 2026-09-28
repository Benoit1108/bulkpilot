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
├── Shared/          noyau partagé minimal (identifiants, événements, bus, santé)
├── Identity/        comptes, profils, sessions, 2FA
├── Nutrition/       aliments, recettes, repas, journal, objectifs
├── Training/        exercices, programmes versionnés, séances, séries, PR
├── Progression/     pesées, mensurations, photos, objectifs de poids
├── Coach/           moteur de règles, analyses, recommandations
├── Notification/    préférences, envoi, horaires silencieux
├── Media/           stockage de fichiers privés
├── Export/          exports et suppression de compte
└── System/          préoccupations opérationnelles (santé du service) — pas un contexte métier (ADR-0013)
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

Les handlers ne portent aucun attribut framework : ils implémentent `CommandHandler`, `QueryHandler`
ou `DomainEventSubscriber`, et `Kernel::build()` les rattache au bon bus (y compris les services de test).
La validation des entrées se fait sur les DTO de `Presentation` (API Platform → 422), pas sur les bus.

Sémantique des événements (ADR-0014) : un handler publie ses événements via `EventBus::publish()` ; ils sont
différés jusqu'au succès de la commande et abandonnés si elle échoue. Si un abonné synchrone échoue **après** le commit,
la commande reste un succès (sinon le client réessaierait et appliquerait l'écriture deux fois) : l'échec est journalisé
en `error`. Une réaction qui ne doit pas être perdue est routée vers le transport `async` (retries + file `failed`).
Ces comportements sont couverts par `tests/Integration/Shared/Infrastructure/Bus/BusWiringTest.php`.

Le transport `async` (Doctrine, table `messenger_messages`) est prêt pour les tâches lourdes
(rapports, exports, photos, IA, notifications). Rien n'y est routé tant qu'aucun message asynchrone n'existe ;
un service `worker` sera ajouté au compose à ce moment-là.

### Persistance

- Mapping Doctrine **XML** dans `<Context>/Infrastructure/Persistence/Doctrine/Mapping` : le domaine reste pur (ADR-0009).
- Identifiants UUID v7 (ordonnés dans le temps), encapsulés dans des value objects héritant de `Shared\Domain\Identifier\Uuid`.
- Migrations versionnées dans `api/migrations`, exécutées au démarrage du conteneur.

### FrankenPHP worker mode

Le kernel reste en mémoire entre les requêtes. Règles :

- aucun état lié à une requête ou à un utilisateur dans une propriété de service (services `final readonly` par défaut) ;
- un service qui doit garder un cache mémoire implémente `ResetInterface` ;
- l'EntityManager est réinitialisé par Symfony entre les requêtes (registre Doctrine tagué `kernel.reset`).

En dev, le worker redémarre quand `src/` ou `config/` changent ; après un changement de `vendor/`, `make restart`.
Le healthcheck du conteneur interroge PHP (`/api/health` sur un listener interne), un worker bloqué est donc détecté.
La revue de code vérifie ces points ; des tests dédiés au worker arriveront avec le premier état sensible (Identity).

## Frontend

```text
pwa/app/
├── pages/          routes (une page par section, fines) + reglages/
├── layouts/        shell : navigation, en-tête, bandeau de connectivité, contenu
├── components/
│   ├── ui/         design system (UiButton, UiCard, UiEmptyState…)
│   └── shell/      ShellNav, ShellConnectivityBanner, ShellUpdatePrompt
├── features/       code par fonctionnalité (appels API typés, état dérivé) : shell/, system/
├── composables/    useApi, useTheme, usePwaUpdate, useEventListener
├── services/api/   client HTTP unique + normalisation des erreurs
├── plugins/        initialisations côté client (thème)
├── error.vue       page d'erreur en français, sans détail technique
└── types/          api.d.ts généré depuis l'OpenAPI (ne pas éditer)
```

- **SPA** (`ssr: false`) : le shell est précaché par le service worker et démarre hors ligne (ADR-0005).
- **Client API unique** (`services/api/client.ts`) : cookie de session same-origin, JSON, erreurs normalisées
  en `ApiError` (message utilisateur / message technique / `traceId` = en-tête `X-Request-Id` posé par Caddy).
  Les lectures sont réessayées une fois (coupure réseau, 500/502/503/504), jamais les écritures.
  `asApiError()` récupère l'`ApiError` d'origine derrière l'enveloppe de `useAsyncData`.
- **Types générés** : `make openapi` exporte la spec et régénère `app/types/api.d.ts` ; `make openapi-check` (CI) échoue si elle est périmée.
- **Navigation** : barre basse sur téléphone ; rail vertical dès 48rem ; barre latérale libellée dès 75rem.
  Les Réglages sont séparés des cinq sections (en-tête sur téléphone, bas du rail sinon).
- **Design system** : tokens CSS (`assets/css/tokens.css`), thème sombre par défaut (clair et « système » dans les Réglages),
  accent violet, `--color-accent-fill` pour les surfaces portant du texte blanc (contraste AA), cibles tactiles ≥ 44 px,
  état actif de navigation non porté par la seule couleur.
- **Thème** : `public/theme-init.js` l'applique avant le premier rendu ; `features/shell/theme.ts` centralise les valeurs.
- **Connectivité** : `ShellConnectivityBanner` n'apparaît que si le serveur est injoignable ou dégradé ; le détail est dans Réglages › À propos.
- **Mises à jour** : le service worker ne s'active qu'après validation (« Mettre à jour » / « Plus tard ») ; vérification horaire.

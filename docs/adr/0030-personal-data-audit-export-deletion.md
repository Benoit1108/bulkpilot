# 0030 — Données personnelles : audit, export, suppression du compte

**Contexte.** Spec §9–10, §103–105, §121 : répondre à « pourquoi cette valeur a-t-elle changé ? », exporter ses données
(CSV, Excel, PDF, JSON, photos), supprimer son compte (confirmation forte, export proposé, suppression des données et
des fichiers, sessions révoquées). Chaque contexte garde ses propres données et ne dépend que du noyau partagé.

## Port des données personnelles

`Shared\Application\PersonalData\PersonalData`, implémenté par chaque contexte qui garde des données d'un utilisateur
et collecté par étiquette (`app.personal_data`) :

- `domain()` : le nom du domaine dans l'export (`nutrition`, `training`, `activity`…), celui qu'une portée choisit ;
- `title()` : ce que l'utilisateur lit (« Nutrition »), `null` quand il n'y a rien à exporter (Media, Export) ;
- `periodFiltered()` : si la période d'un export le restreint (le compte et les réglages sont toujours complets) ;
- `erasedLast()` : le contexte qui porte le compte (Identity) est effacé après tous les autres ;
- `export(UserId, ExportScope)` → des `Dataset` (nom de fichier, titre français, colonnes, lignes), filtrés par la
  période quand elle s'applique, prise dans le fuseau de l'utilisateur (une séance à 0 h 30 à Paris appartient à ce jour
  parisien) ; un moment est donné en date-heure, que l'archive écrit dans ce fuseau ;
- `files(UserId, ExportScope)` → les fichiers qui vont avec (photos), sous un nom lisible ;
- `erase(UserId)` : efface tout ce que le contexte garde de l'utilisateur, fichiers compris, et ne change rien la
  seconde fois.

L'export et la suppression parcourent tous les contextes sans en connaître aucun : un contexte ajouté plus tard ajoute
son implémentation, rien d'autre.

## Audit

- **Contexte `Audit`** : une table en **ajout seul** (`audit_events`) et un port partagé,
  `Shared\Application\Audit\AuditTrail::record(userId, action, details, source, occurredOn)`. Chaque contexte y
  inscrit ses actions importantes, là où il sait ce qu'elles veulent dire :
  - Identity : inscription, connexion (`identity.signed_in`, l'appareil), mot de passe essayé sur un compte existant
    (`identity.sign_in_failed`), code du second facteur refusé, mot de passe changé ou réinitialisé, email confirmé,
    2FA activée ou désactivée, codes de secours régénérés ou utilisés, appareil déconnecté depuis la liste, compte
    Google lié ;
  - Nutrition : objectif fixé (`nutrition.target_set`, kcal d'avant et d'après, macros, origine `manual`, `estimate` ou
    `coach`) ;
  - Coach : décision sur une recommandation ;
  - Export et suppression du compte : voir leurs sections.
- **Source** : `user`, `coach` (le Coach, une fois la proposition acceptée : « source: coach, approved_by: user »,
  spec §10) ou `system` (une tâche planifiée).
- **Jamais de secret** dans les détails : ni mot de passe, ni jeton, ni adresse email ; ce qui a changé seulement.
- **Écrit tout de suite** (insertion directe, pas au flush de l'unité de travail), par deux chemins :
  - dans la transaction d'une commande (export, suppression, changement de mot de passe…) : une commande annulée n'en
    laisse pas de trace ;
  - après la validation, par un abonné à un événement de domaine (inscription, 2FA, objectif fixé, décision du Coach) :
    livraison « au mieux » de l'ADR-0014, une trace peut manquer si l'abonné échoue, jamais une trace de trop.
  Un refus de preuve d'identité (mauvais mot de passe à la connexion, code du second facteur refusé, confirmation de la
  suppression refusée : `identity.reauthentication_failed`) est inscrit par une commande à part
  (`RecordAuthenticationFailure`), dispatchée par la présentation après le refus : la commande refusée est annulée, et
  une requête n'écrit rien.
- **Actions fermées** : la liste des actions (`Audit\Domain\Action`) est exposée dans l'OpenAPI, et le journal refuse
  une action qu'il ne connaît pas. Détails : `nutrition.target_set` {`previous_kcal`, `kcal`, `effective_from`,
  `origin`}, `coach.recommendation_decided` {`kind`, `status`}, connexion et appareil déconnecté {`device`},
  `identity.deletion_scheduled` {`due_on`}, `export.requested` {`from`, `to`, `domains`}.
- **Deux lecteurs** : l'utilisateur (« Activité du compte », `GET /api/me/activity`, le plus récent d'abord, curseur
  `(date, id)`), et l'opérateur (le canal de logs `audit`, les mêmes événements). La formulation d'une recommandation
  (le fournisseur IA utilisé, spec §122) reste dans le seul canal de l'opérateur : ce n'est pas une action de
  l'utilisateur.
- **Rétention** : `AUDIT_RETENTION_DAYS` (365 par défaut), purge chaque jour par le worker (programme `audit`).
- **Suppression du compte** : l'implémentation `PersonalData` d'Audit ne supprime pas les événements, elle les
  **pseudonymise** : le propriétaire est remplacé par un HMAC-SHA256 de son identifiant (clé dérivée de
  `kernel.secret`, stable mais irréversible sans elle) et les détails sont vidés ; restent l'action, la source et la
  date. Le canal de l'opérateur nomme lui aussi l'effacement par ce pseudonyme, jamais par l'identifiant ; les lignes
  qu'il a écrites avant, avec l'identifiant, suivent la rétention des logs (fixée en phase 10). Changer
  `kernel.secret` change la clé : les événements d'un compte effacé avant ne se relient plus à ceux d'après. Un événement inscrit après l'effacement (`identity.account_erased`) doit l'être avant l'appel à l'effacement
  d'Audit, ou il garderait l'identifiant.

## Export

- **Contexte `Export`** : une demande (`ExportRequest` : période facultative, domaines, tous par défaut) est
  enregistrée (`POST /api/exports`, `202`) puis construite **par le worker** (`GenerateExport`, transport `async`) :
  elle lit tous les contextes et écrit une archive, jamais dans la requête. Une seule en cours par utilisateur
  (index unique partiel sur `queued` et `processing`, `409 export.in_progress`), 5 par jour
  (`RATE_LIMIT_EXPORT_WRITES` : un jeton n'est pris qu'une fois la demande acceptée, pas pour un refus). Un échec la
  marque `failed` (l'utilisateur la redemande) plutôt que de rejouer un travail lourd.
- **Un travail hors transaction** (bus `job.bus`, sans middleware de transaction) : `GenerateExport` prend l'export
  (`queued` → `processing`, commande courte `StartExport` ; un message rejoué le trouve pris et s'arrête), lit les
  contextes sans transaction (une erreur SQL n'annule rien d'autre), écrit l'archive sur le disque, puis la garde et
  rend l'export prêt par une autre commande courte (`CompleteExport`). En cas d'échec, le fichier peut-être écrit est
  oublié (`Archives::forget`) et l'export passe `failed` (`FailExport`). Un worker qui meurt en cours (mémoire,
  redémarrage) laisse l'export ouvert : la purge du programme `export` (toutes les 15 minutes) le passe `failed` 30
  minutes après la demande.
- **Une archive ZIP**, les mêmes tables sous chaque forme :
  - `donnees.json` : tout, valeurs telles quelles (pour une machine, une réimportation) ;
  - `csv/`, des noms français (`compte.csv`, `entrainement-seances.csv`, `activite.csv`), rien pour une table vide :
    UTF-8 avec BOM, séparateur `;`, virgule décimale, moments « 2026-09-30 15:53 » dans le fuseau de l'utilisateur
    (Excel en français l'ouvre tel quel et y lit des dates) ;
  - `bulkpilot.xlsx` : une feuille par table, nommée d'après elle (le domaine devant seulement si deux tables ont le même
    titre), ligne d'en-tête figée, vraies cellules de date (jj/mm/aaaa hh:mm) (OpenSpout, écrit en flux, MIT) ;
  - `synthese.pdf` : ce que contient l'export et les 20 premières lignes de chaque table, nombres et dates à la
    française, tables vides regroupées (Dompdf, LGPL-2.1, sans ressource distante ni PHP, polices DejaVu). Non balisé :
    les CSV et le classeur en sont la version accessible ;
  - les codes sont écrits en mots (Manuel, Estimation, Coach ; Cru, Cuit ; statuts, niveaux, catégories…), et
    l'activité en colonnes « Date ; Action ; Par ; Détails », comme l'application la formule ;
  - `photos/2026-09-28-face-1a2b3c4d.webp` : les photos telles que gardées (réencodées, sans métadonnées, ADR-0027).
  Un texte que le tableur exécuterait comme formule (`=`, `+`, `-`, `@` en tête, un nom d'aliment tapé par
  l'utilisateur) est préfixé de `'` dans les CSV et le classeur (injection de formule) ; le JSON garde la valeur.
- **Jamais de secret** : ni empreinte de mot de passe, ni secret TOTP, codes de secours, jetons ou identifiants de
  session ; les appareils sont exportés par leur nom et leurs dates.
- **Archive privée** : gardée par Media comme les photos (port `Shared\Application\Media\Archives`, clé opaque
  `{uuid}.zip`, propriétaire vérifié), téléchargée par un **lien signé de 5 minutes**, demandé par
  `POST /api/exports/{id}/download` (inscrit `export.downloaded` à l'audit ; `export.requested` l'est à la demande).
  **Gardée 24 h** : passé ce délai elle n'est plus proposée, et la purge (programme `export`) la supprime après
  validation, dans le worker (`ExportExpired` routé sur `async` : rejoué jusqu'à ce que ce soit fait).
- **Jamais tout en mémoire** : chaque partie (le JSON écrit table par table, les CSV, le classeur, le PDF, les photos
  copiées en flux) est écrite sur le disque puis ajoutée au ZIP, et l'archive est envoyée au stockage en flux
  (`MediaStorage::writeFile`).
- **Les implémentations `PersonalData`** d'Identity, Nutrition, Training, Progression, Coach et Notification sont des
  adaptateurs d'Infrastructure (SQL via DBAL, `Shared\Infrastructure\Persistence\Doctrine\PersonalRows`) : lire et
  effacer des tables entières d'un propriétaire ne passe pas par les agrégats. Les catalogues partagés (CIQUAL, Open
  Food Facts, bibliothèque d'exercices) ne sont à personne et restent. Media n'exporte rien (les autres exportent ce
  que ses fichiers veulent dire) et efface tous les fichiers du propriétaire **après validation** (`MediaFileErased`,
  routé sur `async` : un stockage indisponible est réessayé, puis gardé dans la file `failed` avec les clés à supprimer,
  seule trace une fois les lignes effacées) ; Export efface ses demandes (leurs archives sont des fichiers de Media).
  Un fichier de Media sait ce qu'il est (`kind` : photo ou archive) : une bibliothèque ne sert jamais l'autre sorte.
- **Limites** : l'archive est envoyée au stockage dans la transaction courte de `CompleteExport` (le temps de
  l'envoi) ; les lignes de chaque table sont lues d'un coup par contexte (des dizaines de milliers pour des années d'un
  utilisateur : acceptable) ; la file `failed` peut garder, le temps que l'opérateur la rejoue ou la vide, des messages
  portant l'identifiant d'un compte effacé.

## Suppression du compte

- **Confirmation forte** (`POST /api/me/deletion`) : le mot de passe, et le second facteur quand il est actif (code de
  l'application ou code de secours, brûlé). Une session seule ne suffit pas ; les essais partagent le budget des autres
  confirmations de mot de passe. Un compte créé par Google, sans mot de passe, envoie un mot de passe vide et doit
  s'être connecté sur cet appareil dans les 10 dernières minutes, par une session toujours active
  (`identity.reauthentication_required` sinon : la PWA fait repasser par Google puis réessaie). Une confirmation
  refusée est inscrite à l'activité (`identity.reauthentication_failed`). La PWA propose l'export juste avant. La réponse (`202`) donne
  `deletionDueAt`.
- **Fermeture immédiate** : toutes les sessions sont révoquées, cet appareil compris, et le compte sort de la liste des
  comptes du travail planifié (plus de vérification de notifications ni d'analyse nocturne). La date d'effacement est
  gardée sur `User` (`deletion_due_at`, 7 jours plus tard) ; redemander garde la première.
- **Annulation** : se reconnecter pendant le délai (mot de passe, Google, second facteur : tous passent par l'ouverture
  d'une session) garde le compte. La réponse de connexion le dit une fois (`deletionCancelled`), le retour de Google
  par `?suppression_annulee=1`.
- **Effacement** : le planificateur d'Identity (`scheduler_identity`, toutes les heures) envoie un `EraseAccount`
  asynchrone par compte échu ; un échec est rejoué seul. Le gestionnaire **verrouille la ligne du compte**
  (`SELECT … FOR UPDATE`) : une connexion qui annulerait la suppression attend la fin de l'effacement, puis ne trouve
  plus le compte (ou, validée juste avant, le garde : l'effacement ne fait alors rien). Il vérifie que le compte est
  toujours échu,
  inscrit `identity.account_erased` à l'audit **avant** d'effacer (l'audit le pseudonymise avec le reste), appelle
  l'`erase` de chaque contexte, Identity en dernier (le compte est la clé des autres), dans une transaction ; les
  fichiers partent une fois validée. L'adresse et le prénom, relevés avant, servent au dernier email, envoyé après la
  validation seulement. Les sessions HTTP des appareils (table `sessions` de `PdoSessionHandler`) qui portent
  l'identifiant sont supprimées avec le compte. Un message déjà en file pour un compte fermé (une vérification de
  notifications, une analyse nocturne) ne produit rien. Chaque effacement est aussi retenu à côté des sauvegardes
  (ADR-0031), pour qu'une restauration ne fasse pas revenir le compte.
- **Emails** à chaque étape : suppression prévue (la date et l'heure dans le fuseau de l'utilisateur, tous les appareils
  déconnectés, le lien de connexion pour l'annuler, « Ce n'est pas toi ? »), suppression annulée (« Une connexion à ton
  compte a annulé sa suppression », le jour et l'heure ; la consigne adaptée à un compte sans mot de passe), compte
  supprimé. Dans le worker, les emails partent après chaque
  message traité (pas à la fin du processus).
- Après l'effacement, la même adresse peut créer un nouveau compte.
- **Les délais** (7 jours, 24 h d'un export, rétention de l'activité, 10 minutes) sont donnés à la PWA par
  `GET /api/me/data-policy` : elle n'en affirme aucun d'elle-même.

## Conséquences

Le noyau partagé porte trois contrats de plus (données personnelles, audit, archives) ; chaque contexte teste son
implémentation. L'audit devient la réponse, pour l'utilisateur, à « pourquoi cette valeur a changé ».

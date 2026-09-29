# 0027 — Photos de progression : stockage privé, métadonnées retirées, liens signés

**Contexte.** Spec §60–61, §90–91, §100, §121 : photos de progression (ajout, suppression, téléchargement, comparaison côte
à côte ou au curseur, sessions « Début — 45 kg vs Aujourd'hui — 51,2 kg »), jamais publiques ; en production un bucket
S3-compatible privé, en développement le disque local ; Symfony délivre des liens signés de courte durée. Ce sont des
données parmi les plus sensibles de l'application ; une photo de téléphone porte souvent la position GPS et l'appareil
dans ses métadonnées EXIF.

**Décision.**

- **Contexte `Media`** : le fichier (stockage, clés, liens) ; **Progression** garde le sens (photo d'un jour, poids et
  mensurations associés, sessions) et ne référence que l'identifiant du média. Stockage derrière une interface
  (`MediaStorage`) : système de fichiers local en dev / test, S3-compatible en production (`async-aws/s3`, sans couche
  Flysystem : trois opérations suffisent — écrire, supprimer, présigner), une implémentation en mémoire en test unitaire.
- **Échanges Progression ↔ Media** par un port du noyau partagé, implémenté par Media (`PhotoLibrary`) :
  `Shared\Application\Media\Photos` —
  - `store(UserId, MediaId, chemin)` → `StoredPhoto` (dimensions) : vérifie, réencode et garde le fichier ;
  - `links(UserId, MediaId, nom de téléchargement)` → `PhotoLinks` (photo, miniature, téléchargement, expiration) ;
  - `linksFor(UserId, [MediaId => nom])` → les liens de plusieurs photos en une lecture (une galerie) ;
  - `delete(UserId, MediaId)` : suppression définitive, idempotente ;
  - `forget(MediaId)` : retire les fichiers écrits pour une photo dont l'enregistrement n'a pas été validé.

  L'envoi arrive sur une route **Progression** (`POST /api/progression/photos` : fichier, date, pose) qui choisit
  l'identifiant et confie le fichier au port ; le poids et les mensurations d'une session sont ceux du jour, lus au
  moment de l'affichage. **Media est propriétaire** du fichier et de son propriétaire (`UserId`), et vérifie lui-même, à
  chaque lien ou suppression, que le média appartient bien à l'utilisateur qui le demande (`MediaNotFound` sinon, comme
  pour un média absent). Si la commande échoue après l'écriture, le processeur appelle `forget` ; si l'écriture du
  fichier échoue, `store` défait ce qu'il a écrit. Supprimer une photo de progression lève `ProgressPhotoRemoved` : le
  fichier est effacé **après la validation** de la transaction (ADR-0014), jamais avant — une photo n'est pas perdue si
  la suppression échoue. La suppression du compte (phase 8) passe par un événement de domaine auquel Media s'abonne.
- **Aucun original conservé** : à l'envoi, l'API vérifie le type réel par ses premiers octets (JPEG, PNG ou WebP, pas
  l'extension), limite la taille (15 Mo), les dimensions (8 000 px de côté) et le nombre total de pixels (24 Mpx) **avant de
  décoder**, refuse les images animées ou à plusieurs images (PNG animé repéré à ses blocs, WebP animé à son en-tête
  VP8X), puis **décode et réencode** l'image avec **GD** (surface d'attaque plus petite qu'ImageMagick et ses nombreux
  décodeurs) : WebP, 2 048 px de côté au plus (réduite avant d'appliquer l'orientation EXIF, pour borner la mémoire) et
  une miniature (480 px). Le réencodage supprime toutes les métadonnées (EXIF, GPS, XMP) : rien n'est écrit avant. La PWA
  réduit déjà l'image avant l'envoi (moins de données en salle), sans que le serveur s'y fie.
- **Traitement synchrone** : une photo réencodée en moins d'une seconde n'a pas besoin de file d'attente, et un traitement
  différé obligerait à stocker un temps l'original et ses métadonnées. La file Messenger reste pour les exports (phase 8).
- **Clés opaques** (`{uuid}.webp`, sans l'identifiant de l'utilisateur qui apparaîtrait sinon dans chaque lien), jamais le
  nom du fichier envoyé ; le propriétaire est en base. Bucket privé, chiffrement au repos du
  fournisseur. **Liens signés de 5 minutes** : URL présignée S3 en production ; en dev, une route Symfony vérifiant une
  signature HMAC de la clé **et** de l'expiration, comparée en temps constant, route absente en production (même contrat).
  Chaque lien est émis après vérification du propriétaire. Le téléchargement force un nom neutre
  (`ResponseContentDisposition`, « photo-2026-09-29.webp »).
- **Pas de cache** des photos par le service worker ni par le navigateur : `Cache-Control: private, no-store` sur la route
  locale **et** sur les liens présignés (`ResponseCacheControl`). Hors ligne, les photos ne s'affichent pas.
- **Bornes** : 500 photos par utilisateur au plus (le nombre, pas l'espace : chaque photo réencodée pèse au plus
  quelques centaines de Ko), envois limités par minute (429, comme les autres écritures de Progression).
- **Suppression réelle** : supprimer une photo efface le fichier et sa miniature ; la suppression du compte (phase 8)
  efface tous les fichiers dont il est propriétaire (liste en base). Le bucket n'a **ni versionnage ni verrouillage d'objet**
  (sinon une suppression ne pose qu'un marqueur) ; les sauvegardes du bucket, s'il y en a, expirent sous 30 jours. Aucune photo n'est jamais envoyée à un fournisseur IA sans consentement explicite (§122).

**Mise en œuvre (phase 5).** Stockage local en dev et en test (`var/media/<env>`, route signée `GET /api/media/{clé}`
déclarée pour `dev` et `test` seulement). Son contrôleur est en Infrastructure de Media, à côté du stockage local qu'il
sert : c'est un adaptateur de ce stockage, pas une ressource de l'API. En production, S3 par `async-aws/s3` (choisi par
`MEDIA_STORAGE=s3` ; l'image refuse de démarrer sans bucket, clés et point d'accès public en https), avec un client de
signature distinct pour le domaine public des liens. La pile E2E sur l'image de production passe par une passerelle S3
compatible (`versity/versitygw`, qui vérifie les signatures SigV4, liens présignés compris) derrière un proxy TLS : le
navigateur lit donc les photos comme en production, depuis un autre domaine.
L'application n'a pas encore de CSP : `img-src` (bucket, `blob:`) sera posé avec elle en phase 8.

**Conséquences.** Une dépendance d'image côté PHP (extension GD avec WebP) dans l'image Docker ; la CSP doit
autoriser le domaine du bucket et `blob:` (aperçu et réduction avant envoi) en `img-src`, et le bucket en `connect-src` avec une
règle CORS limitée à l'origine de l'application si le téléchargement passe par `fetch`. Le coût mémoire du décodage d'une grande photo est borné par la limite de
dimensions, vérifiée avant de décoder.

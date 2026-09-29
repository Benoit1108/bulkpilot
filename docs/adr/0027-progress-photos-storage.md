# 0027 — Photos de progression : stockage privé, métadonnées retirées, liens signés

**Contexte.** Spec §60–61, §90–91, §100, §121 : photos de progression (ajout, suppression, téléchargement, comparaison côte
à côte ou au curseur, sessions « Début — 45 kg vs Aujourd'hui — 51,2 kg »), jamais publiques ; en production un bucket
S3-compatible privé, en développement le disque local ; Symfony délivre des liens signés de courte durée. Ce sont des
données parmi les plus sensibles de l'application ; une photo de téléphone porte souvent la position GPS et l'appareil
dans ses métadonnées EXIF.

**Décision.**

- **Contexte `Media`** : le fichier (stockage, clés, liens) ; **Progression** garde le sens (photo d'un jour, poids et
  mensurations associés, sessions) et ne référence que l'identifiant du média. Stockage derrière une interface
  (`MediaStorage`) : système de fichiers local en dev / test, S3-compatible en production (Flysystem), `Fake` en test unitaire.
- **Échanges Progression ↔ Media** par un port du noyau partagé (`Shared\Application\Media\Photos` : `store(UserId, fichier)`
  → `MediaId`, `link(UserId, MediaId)` → lien signé, `delete(UserId, MediaId)`), implémenté par Media. L'envoi arrive sur une
  route **Progression** (`POST /api/progress-photos` : fichier, date, poids et mensurations associés) qui confie le fichier au
  port ; **Media est propriétaire** du fichier et de son propriétaire (`UserId`), et vérifie lui-même, à chaque lien ou
  suppression, que le média appartient bien à l'utilisateur qui le demande. Supprimer une photo de progression appelle
  `delete` dans la même requête (suppression réelle, pas différée) ; la suppression du compte (phase 8) passe par un
  événement de domaine auquel Media s'abonne.
- **Aucun original conservé** : à l'envoi, l'API vérifie le type réel par ses premiers octets (JPEG, PNG ou WebP, pas
  l'extension), limite la taille (15 Mo), les dimensions et le nombre total de pixels (40 Mpx) **avant de décoder**, refuse
  les images animées ou à plusieurs images, puis **décode et réencode** l'image avec **GD** (surface d'attaque plus petite
  qu'ImageMagick et ses nombreux décodeurs) (WebP, 2 048 px de côté au plus, orientation appliquée) et
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
- **Bornes** : nombre de photos et espace par utilisateur plafonnés, envois limités par minute (429, comme les autres écritures).
- **Suppression réelle** : supprimer une photo efface le fichier et sa miniature ; la suppression du compte (phase 8)
  efface tous les fichiers dont il est propriétaire (liste en base). Le bucket n'a **ni versionnage ni verrouillage d'objet**
  (sinon une suppression ne pose qu'un marqueur) ; les sauvegardes du bucket, s'il y en a, expirent sous 30 jours. Aucune photo n'est jamais envoyée à un fournisseur IA sans consentement explicite (§122).

**Conséquences.** Une dépendance d'image côté PHP (extension GD avec WebP) dans l'image Docker ; la CSP doit
autoriser le domaine du bucket et `blob:` (aperçu et réduction avant envoi) en `img-src`, et le bucket en `connect-src` avec une
règle CORS limitée à l'origine de l'application si le téléchargement passe par `fetch`. Le coût mémoire du décodage d'une grande photo est borné par la limite de
dimensions, vérifiée avant de décoder.

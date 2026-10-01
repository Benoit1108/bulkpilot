# 0031 — Sauvegardes de la base : chiffrées, hors du serveur, restaurées chaque semaine

**Contexte.** Spec §106 : en production, des sauvegardes PostgreSQL automatiques, une rétention configurable, une
vérification régulière et une procédure de restauration documentée — « un backup non testé n'est pas une stratégie ».
La base contient des données de santé (poids, alimentation, mensurations) : une sauvegarde qui fuit est aussi grave
qu'une base qui fuit. Les photos sont déjà dans un bucket S3-compatible privé (ADR-0027), gardé par le fournisseur.

**Décision.**

- **`pg_dump` au format custom**, par le client PostgreSQL de la version majeure du serveur, installé dans l'image
  depuis le dépôt de PostgreSQL (Debian en livre un plus ancien) ; `pg_restore` pour la restauration. Contexte `System`
  (opérationnel) : ports `DatabaseDump`, `BackupCipher`, `BackupStore`, adaptateurs en Infrastructure.
- **Chiffrées avant de quitter le serveur** : libsodium, flux secret XChaCha20-Poly1305 par blocs de 64 Kio, clé
  dérivée de `BACKUP_PASSPHRASE` par Argon2id (sel et coût dans l'en-tête du fichier). Chaque bloc est authentifié et le
  dernier marqué : un fichier modifié, réordonné ou tronqué est refusé, jamais restauré à moitié. Sans la phrase de
  passe, une sauvegarde est illisible — elle se garde **hors du serveur et hors du bucket** (gestionnaire de secrets).
- **Un bucket à part**, avec ses propres clés (`BACKUP_S3_*`) : il peut vivre chez un autre fournisseur, et la fuite des
  clés des photos n'ouvre pas les sauvegardes (ni l'inverse). Le démarrage en production refuse le bucket des photos.
  Clés `db/AAAA/MM/JJ/HHMMSS.dump.enc` (UTC) : lister le préfixe les trie par date.
- **Chaque nuit** (02 h 30 UTC, par le worker, ADR-0029) : sauvegarde, puis suppression de celles plus vieilles que
  `BACKUP_RETENTION_DAYS` (30) — **jamais la plus récente**, pour qu'une restauration reste possible même si les
  sauvegardes s'arrêtent. Un travail (bus `job.bus`) sans transaction ouverte autour du dump et de l'envoi ; un fichier
  temporaire laissé par un passage interrompu est supprimé au suivant. Mieux encore en production (docs/DEPLOYMENT.md) :
  une règle de cycle de vie du bucket et une clé sans droit de suppression, avec versionnage et verrouillage d'objet sur
  la durée de rétention — une intrusion ne peut alors plus effacer les sauvegardes.
- **Chaque semaine** (dimanche 04 h 30 UTC) : la dernière sauvegarde est téléchargée, déchiffrée et restaurée dans une
  base jetable, créée pour l'occasion et toujours supprimée ensuite. Elle doit avoir moins de 48 h, ses tables doivent
  être celles de la base vivante (les mêmes à la même migration), sa dernière migration connue de la base vivante et
  les comptes lisibles ; elle compte les lignes de quelques tables clés (comptes, journal, séances, pesées, fichiers,
  activité). Tout écart est une erreur journalisée (`system.backup_verification_failed`) et une commande
  en échec — la supervision (phase 10) s'y branchera.
- **En CI**, `make e2e-prod` sauvegarde la base de l'image de production vers un bucket de la passerelle S3 de test
  puis la vérifie, en exigeant les comptes créés par les tests (`--min-accounts=1`), et vérifie qu'une sauvegarde
  illisible (une autre phrase de passe) est bien refusée : une sauvegarde qui ne se restaure plus fait échouer le
  pipeline.
- **Une restauration ne ressuscite aucun compte effacé** : chaque effacement laisse, après validation, un objet vide
  `erasures/AAAA-MM-JJ/<id>` dans le bucket des sauvegardes (supprimé 7 jours après la rétention des sauvegardes, quand
  plus aucune ne peut contenir le compte). Après une restauration, `app:backup:reapply-erasures` efface à nouveau ces
  comptes, avant la réouverture ; la vérification hebdomadaire compte ceux que la restauration ferait revenir. Les
  données d'un compte effacé restent donc dans les sauvegardes jusqu'à 30 jours (la rétention).
- **Seul le worker a les secrets des sauvegardes** (`BACKUP_*`) : le conteneur web ne les reçoit pas (il refuse de
  démarrer s'il les a), une intrusion par le serveur web ne peut ni lire ni supprimer les sauvegardes. Les clés du bucket
  des sauvegardes doivent être distinctes de celles des photos.
- **L'en-tête d'une sauvegarde est borné** : un coût de dérivation hors de [interactif, sensible] ou un bloc de longueur
  impossible est refusé avant tout calcul (une sauvegarde forgée ne peut épuiser la mémoire du worker).
- La connexion de `pg_dump` garde les réglages TLS de `DATABASE_URL` (`sslmode`, certificats).
- **Obligatoires en production** : le worker refuse de démarrer sans `BACKUP_ENABLED=1`, le bucket, ses clés (autres
  que celles des photos) et une phrase de passe d'au moins 32 caractères. Désactivées en développement.

**Conséquences.** L'image embarque le client PostgreSQL (quelques Mo). La base est entière dans chaque sauvegarde
(petite pour une application personnelle ; au-delà, un archivage continu des WAL remplacerait ce choix). Le compte de la
base doit pouvoir créer une base (`CREATEDB`) pour la restauration de contrôle. Les photos ne sont pas sauvegardées par
l'application : leur durabilité est celle du fournisseur du bucket. Perdre la phrase de passe, c'est perdre les
sauvegardes : la changer ne rend illisibles que les nouvelles avec l'ancienne, les anciennes restent lisibles avec
l'ancienne phrase.

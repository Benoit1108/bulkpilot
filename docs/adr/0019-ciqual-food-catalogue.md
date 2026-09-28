# 0019 — Table CIQUAL comme base d'aliments génériques

**Contexte.** Le journal a besoin d'aliments génériques fiables, en français, distinguant cru et cuit, hors ligne du point de vue
de l'utilisateur (pas d'appel externe à chaque recherche). Open Food Facts (ADR-0007, phase 3) couvre les produits emballés, pas « riz blanc, cuit ».

**Décision.**

- Import de la table **Ciqual 2020 de l'Anses** (Licence Ouverte Etalab 2.0 : réutilisation libre, **source citée** dans l'interface)
  dans la table `foods`, source `ciqual`, clé naturelle `(source, source_reference)` = code CIQUAL.
- Commande `nutrition:catalogue:import [dossier|url]` (`make ciqual`) : téléchargement de l'archive officielle en HTTPS **sans redirection**,
  bornée en taille (20 Mo) et en durée, **vérifiée par son SHA-256** (constante `OFFICIAL_ARCHIVE_SHA256`, à mettre à jour avec l'URL lors
  d'une nouvelle version ; une autre URL https:// passée explicitement n'est pas vérifiée) ; extraction des seuls `alim_*`, `alim_grp_*`,
  `compo_*` (pas de chemin arbitraire, 200 Mo décompressés au plus), dossier temporaire supprimé même en cas d'échec ; lecture **ligne à ligne** — les fichiers publiés sont en windows-1252
  et ne sont pas du XML bien formé (« < 2,2 » non échappé). Port `FoodCatalogue` côté application, adaptateur `CiqualCatalogue`.
- Constituants retenus : énergie règlement UE 1169/2011 (kcal, à défaut Jones), protéines, glucides, lipides, fibres, sucres, AG saturés, sel.
  Valeurs « traces » et « < x » → 0 ; « - » → inconnu. Sans énergie mais avec toutes les macros : énergie calculée (4 / 4 / 9, fibres 2 kcal/g).
  Une macro inconnue d'un aliment qui a une énergie vaut 0 (boissons, huiles). Sucres ou saturés incohérents avec la macro sont ignorés
  plutôt que de perdre l'aliment. Aliments sans énergie ni macros complètes : ignorés et listés (`-v`) — 93 sur 3 185 en 2020.
- Import **idempotent** (création ou rafraîchissement, même transaction) ; les entrées du journal gardent leur instantané.
- Recherche : colonne `search_key` (minuscules, sans accents ni ligatures), index **GIN pg_trgm** créé par la migration
  (le mapping déclare un index ordinaire du même nom pour que le schéma reste « à jour »). Tous les mots d'au moins 2 lettres
  doivent apparaître (6 mots au plus : une requête faite de lettres isolées ne parcourt pas toute la table) ;
  classement : aliments personnels, préfixe, similarité trigramme, noms courts.

**Conséquences.** ≈ 3 100 aliments génériques disponibles hors dépendance réseau ; une nouvelle version CIQUAL se charge en relançant la commande.
Les tests et l'E2E utilisent un extrait de 12 aliments des vrais fichiers (`api/tests/Fixtures/ciqual`, octets conservés via `.gitattributes`).

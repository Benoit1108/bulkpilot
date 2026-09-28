# 0022 — Scan de code-barres et produits Open Food Facts

**Contexte.** Spec §20–21 : scan EAN → Symfony → base de produits → normalisation → **confirmation** → journal ; jamais d'ajout
silencieux, jamais d'appel direct du front à Open Food Facts. ADR-0007 a posé le port ; il reste à choisir le lecteur, le stockage et les limites.

**Décision.**

- **Lecture** dans la PWA : `BarcodeDetector` natif s'il lit l'EAN-13 (Chrome Android), sinon **zxing-wasm** chargé à la demande
  (Safari iOS…), dont le binaire est servi par l'application elle-même (jamais de CDN) et gardé en cache par le service worker.
  EAN-13, EAN-8, UPC-A ; UPC-E exclu (confondu avec un EAN-8). La saisie manuelle du code est toujours proposée.
- **Code-barres** (`Barcode`) : 8, 12, 13 ou 14 chiffres, clé GS1 vérifiée, jamais uniquement des zéros, ramené à sa forme la plus
  courte (UPC-A et GTIN-14 en 0… → EAN-13, code complété de zéros autour d'un EAN-8 → EAN-8) : un produit, un code.
- **Port `FoodDatabaseProvider`** : `OpenFoodFactsProvider` (API v2, champs utiles seulement, User-Agent identifié `OPEN_FOOD_FACTS_USER_AGENT`,
  délais courts, pas de redirection, kJ → kcal ; valeur négative ou non finie = inconnue ; portion retenue seulement si elle est en grammes),
  derrière `CachedFoodDatabaseProvider` (produit 1 jour, inconnu 1 h, pannes non gardées ; cache **par instance**, `cache.app`) puis
  `RateLimitedFoodDatabaseProvider` : **90 lectures / min pour toute l'application** (Open Food Facts demande < 100), au-delà « base injoignable ».
  Scans et confirmations limités à 30 / min par compte.
- **Faux fournisseur** : `FakeFoodDatabaseProvider` (dev, tests, E2E) est volontairement **dans l'image de production** — l'E2E tourne sur
  cette image. Choix par `FOOD_DATABASE` ; l'entrypoint refuse `fake` en production sauf `ALLOW_FAKE_PROVIDERS=1` (`docker-compose.test.yml`).
- **Scan** (`POST /api/nutrition/barcode/scan`) : l'aliment déjà connu (celui de l'utilisateur d'abord, puis le produit partagé),
  sinon la **proposition** normalisée avec les valeurs manquantes (`missing`) et, si elles ne tiennent pas ensemble, la valeur suspecte
  (`suspect` : `sugars`, `saturatedFat`, `macros`…, clé stable que la PWA traduit). Rien n'est enregistré.
- **Confirmation** (`POST /api/nutrition/barcode/confirm`), deux chemins explicites :
  - telle quelle (`KeepScannedProduct`) : le produit rejoint ou rafraîchit le **catalogue partagé** (source `off`, clé = code) — les valeurs
    sont **relues à la source** côté serveur, jamais reprises du client ; la réponse est ce produit partagé ;
  - corrigée ou complétée : c'est l'**aliment personnel** de l'utilisateur, créé ou modifié par les mêmes cas d'usage
    (`CreateCustomFood` / `EditCustomFood`) qu'un aliment saisi à la main, donc avec les mêmes règles. Un code désigne au plus un de ses
    aliments non supprimés (règle applicative + index unique partiel `foods_owner_barcode_unique`) ; le code des corrections, s'il est donné,
    doit être celui scanné.
  - Deux confirmations simultanées d'un nouveau produit, ou de corrections : la contrainte unique refuse la seconde → 409
    `shared.concurrent_update`, qu'il suffit de relancer.
- Attribution **Open Food Facts (ODbL)** affichée à côté des valeurs proposées et dans la recherche.

**Conséquences.** Un produit est demandé à Open Food Facts une fois par jour au plus, quel que soit le nombre de personnes qui le scannent ;
un produit confirmé est retrouvé ensuite par la recherche. Un produit partagé n'est pas modifiable : on le corrige pour soi.

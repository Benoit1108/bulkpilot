# 0032 — Données de démonstration et contrôles de performance

**Contexte.** Spec §134 : des données de démonstration pour le développement (utilisateur, aliments, recettes,
exercices, programme PPL, séances, progression, recommandations du Coach), **jamais disponibles en production**.
Spec §119 et phase 9 : un tableau de bord rapide, une saisie Training instantanée, des index PostgreSQL adaptés, pas
de N+1 Doctrine, des tests de performance. Les deux se rejoignent : un historique de plusieurs années est la seule
façon de voir un N+1 ou un index manquant.

**Décision.**

- **Outillage de dev, hors de `src/`** (`api/dev/Demo`, espace `App\Dev\Demo`, chargé par `autoload-dev` comme les
  tests) : la commande `app:demo:seed` et son `DemoSeeder`
  construisent un compte jour après jour **à travers les vrais cas d'usage** (bus de commandes), l'horloge déplacée à
  chaque instant (`Symfony\Component\Clock\Clock::set`) : chaque règle s'applique comme si l'utilisateur avait vécu
  ces journées (objectif fixé « aujourd'hui », séances plausibles, fenêtres du Coach), heure après heure dans
  l'ordre de la journée (pesée, mensurations et analyse du lundi avant le petit-déjeuner, la séance avant le dîner).
  Une seule exception : le compte est inscrit et vérifié directement, l'inscription par l'API envoyant le lien de
  vérification par email, qu'un seed ne peut pas suivre. Aucune écriture SQL directe, donc aucune donnée qu'un vrai
  usage ne pourrait produire. Comme les tests, il utilise tous les contextes : il n'est
  donc **pas un contexte** et n'entre pas dans les règles Deptrac (qui ne portent que sur `src/`, et restent
  inchangées) ; aucun code de `src/` ne peut en dépendre, faute de le charger en production.
- **Jamais en production**, à trois niveaux : `api/dev/` n'est pas copié dans l'image (`.dockerignore`) ni chargé
  sans les dépendances de dev, ses services ne sont déclarés que pour `dev` et `test` (`config/services_dev.yaml`,
  `config/services_test.yaml`, eux aussi hors de l'image ; `services.yaml` ne nomme rien de `App\Dev` ni de
  `App\Tests`), et la commande refuse de tourner si `APP_ENV=prod`.
- **Compte** `demo@bulkpilot.local` / `demo-bulkpilot-2026`, 3 ans par défaut (`--years`, `--days`, `--email`) ;
  `--accounts=N` en sème d'autres (`demo2@…`), chacun sa vie (même forme, autres chiffres), `--number=N` un seul
  (plusieurs processus côte à côte). Une seule fois par adresse. Aléatoire à graine fixe : deux seeds donnent les
  mêmes données. Les notifications d'avant la dernière semaine sont lues.
- **Budgets de requêtes SQL** (tests d'API, `tests/Api/Performance`) : un middleware DBAL de test compte les
  instructions de chaque requête ; chaque écran principal a un budget (le compte mesuré + 2), sur deux mois de
  démo. Un N+1 le dépasse aussitôt. Les mêmes requêtes, et les écritures d'une séance, sont **expliquées**
  (`EXPLAIN`, parcours séquentiels désactivés) sur plusieurs comptes : un parcours séquentiel d'une table qui grandit,
  ou un index parcouru sans condition (pour trier, par exemple), veut dire qu'aucun index ne lui va.
- **Charge** : `make perf` (et la CI) monte l'image de production sur sa propre pile, y sème 5 comptes de 3 ans par
  l'image de dev (le seul moyen d'avoir la commande sans l'embarquer en production ; un processus par compte, son
  cache dans un tmpfs : la pile de dev n'est jamais touchée), puis lance **k6** : un lecteur par compte (tableau de
  bord, analyses lancées à l'ouverture, pages principales, recherche d'aliments), un écrivain qui ajoute des lignes
  (pesée, aliment noté, séries), sous les limites de débit par compte ; seuils p95 < `K6_P95_MS` (300 ms en local,
  500 sur un runner de CI partagé) et aucune erreur ni vérification ratée.

**Conséquences.** Un écran qui ajoute une requête par ligne, une requête sans index ou un endpoint devenu lent fait
échouer la CI. Le seed prend environ 1 min 30 pour 3 ans (sur la pile de test). Les mesures de k6 dépendent de la
machine : les seuils sont larges (p95 mesuré autour de 120 ms sur une machine non chargée).

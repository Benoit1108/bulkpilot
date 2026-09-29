# 0028 — Coach : règles déterministes, ports de lecture, validation explicite

**Contexte.** Spec §58–59, §62–74, §122, §130–132 : le Coach analyse le poids, l'alimentation et l'entraînement,
recommande au plus trois choses expliquées (🔴 / 🟠 / 🟢, « Pourquoi ? »), garde l'historique et les décisions, calcule
une adhérence explicable et un rapport par semaine. Il ne change jamais rien sans validation ; Symfony calcule, l'IA
formule, et le moteur doit fonctionner sans IA. Or le Coach ne peut dépendre que du noyau partagé (Deptrac, ADR des
contextes) alors qu'il lit trois autres contextes et, sur acceptation, change un objectif de Nutrition.

**Décision.**

- **Ports du noyau partagé** (`Shared\Application\Coaching`), comme pour les photos (ADR-0027) : chaque contexte
  propriétaire implémente la lecture de ses propres données et le Coach n'en voit que des chiffres.
  - `NutritionReadings::intake(user, du, au)` et `targetOn(user, jour)` (Nutrition) : les jours saisis (kcal,
    protéines), et l'objectif en vigueur un jour (depuis quand, kcal, protéines, rythme de l'estimation) ;
  - `BodyWeight::reading(user, jour)` (Progression) : moyenne 7 jours, vitesse sur 4 semaines (moindres carrés, la même
    que la page Poids), nombre de pesées sur 7 et 28 jours ;
  - `TrainingReadings::between(user, du, au)` (Training) : séances, séances prévues par le programme actif, records,
    exercices qui stagnent — règle du domaine Training (`StallDetector` : ≥ 4 séances depuis le dernier record, vieux
    d'au moins 21 jours, exercice encore travaillé dans les 14 derniers jours) ;
  - `CalorieTargets::adjust(user, depuisKcal, deKcal, jour)` (Nutrition), **seule écriture**, sans retour : un nouvel
    objectif à partir du lendemain de l'utilisateur, source « coach » (tracée par l'audit de Nutrition, spec §10),
    protéines et lipides gardés, les glucides recalculés pour faire exactement les nouvelles kcal
    (`MacroTargets::withEnergy`, règle du domaine Nutrition) ; refusé si l'objectif n'est plus celui sur lequel la
    proposition a été faite (`coach.target_changed`, 409) ou si l'objectif serait invalide (`coach.target_unreachable`,
    422 : hors de 1 000–7 000 kcal, ou moins de kcal que les protéines et lipides n'en donnent). Pour que le lendemain
    d'un appareil déjà passé à minuit (en avance sur le serveur) soit accepté, un objectif peut commencer jusqu'à
    deux jours après le jour du serveur.
  Le Coach traduit ces lectures en faits de son domaine (`Facts`) : ses règles ne dépendent d'aucun autre contexte.
- **Règles déterministes** en Domain (`RuleEngine`), testées sans IA : calories (tendance du poids contre le rythme
  visé, bande de ± la moitié du rythme et au moins 0,2 kg/semaine ; +150 kcal vers l'objectif, 100 kcal en sens
  inverse ; jamais d'augmentation si moins de 95 % de la cible est atteinte — « atteins d'abord » — ni de baisse si elle
  est dépassée de plus de 5 % ; jamais hors des bornes d'un objectif (1 000–7 000 kcal) ; rien les 14 premiers jours
  d'un objectif, ni avec moins de 10 jours saisis sur 14 ou 8 pesées sur 28 ; la part de la cible mangée est arrondie
  vers le bas, pour ne jamais afficher 95 % à côté de « atteins d'abord »), protéines (< 90 % de la cible sur la semaine), journal incomplet, pesées trop rares, séances
  manquées, stagnation (nutrition regardée d'abord, jamais plus de volume), records. Le rythme visé est celui de
  l'estimation de l'objectif nutrition, ou celui des réglages du Coach s'il est fixé.
- **Recommandations** : les trois plus importantes (niveau, puis ordre des règles). Chacune garde les chiffres qui l'ont
  produite, son texte, son fournisseur et sa décision (`pending`, `accepted`, `rejected`, `postponed`, `expired`). Une
  recommandation encore justifiée par les données est gardée telle quelle d'une analyse à l'autre ; une autre expire, et
  toute recommandation expire après 7 jours. « Plus tard » la met de côté 2 jours (elle garde sa place parmi les 3). Un
  type décidé (accepté ou refusé) ne revient pas avant 7 jours. Accepter un conseil ne change rien ; accepter une
  proposition calorique est la seule action du Coach, dans la même transaction que la décision (si l'objectif est
  refusé, la décision n'est pas gardée). Recommandations et réglages sont **versionnés** (verrou optimiste) : de deux
  décisions, ou de deux analyses en même temps, la seconde échoue (409) au lieu de doubler les recommandations ou
  d'écraser une décision. Chaque recommandation annonce `RecommendationMade` (audit du fournisseur) et chaque
  décision `RecommendationDecided`.
- **Analyse à l'ouverture du Coach** (`POST /api/coach/analysis`) : une fois par jour, ou à nouveau sur demande
  (« Relancer »). Toute analyse qui fait tourner les règles est limitée par minute (la première du jour comme les
  suivantes) ; elle ne revient jamais à un jour antérieur au dernier analysé (le jour de l'appareil peut avoir un jour
  d'écart). Pas de tâche planifiée avant la phase 7 (notifications). Aujourd'hui est exclu des fenêtres d'alimentation
  et d'entraînement (la journée n'est pas finie) ; la pesée du matin et l'objectif en vigueur aujourd'hui comptent.
- **Adhérence** sur les 7 derniers jours : moyenne de parts expliquées (jours à ± 10 % des kcal, jours à 90 % des
  protéines, séances faites sur prévues, pesées sur 3). Une part sans attente (pas d'objectif, pas de programme) est
  omise plutôt que comptée comme manquée ; une semaine où rien n'a été saisi, pesé ni fait n'a pas de score. Elle est
  calculée par l'analyse et gardée avec elle : lire le Coach ne relit pas les autres contextes.
- **Rapport hebdomadaire** tiré à la première analyse après la fin de la semaine (lundi–dimanche), puis gardé tel quel :
  poids (moyennes 7 jours de fin de semaine), alimentation, entraînement, verdict (rythme), adhérence et action (la
  consigne, à l'impératif, de la recommandation la plus importante, sinon « Continuer le plan actuel »). La phase 7 le
  montrera au tableau de bord.
- **IA derrière `AiProvider::generateRecommendation(CoachAnalysis): CoachMessage`** (port de `Coach\Application\Port`).
  `CoachAnalysis` ne contient que le type de constat, ses chiffres et la personnalité — rien qui identifie
  l'utilisateur : ce qu'il a tapé (les noms d'exercices d'une stagnation) part sous forme de repères (`{exercise1}`),
  remis dans le message au retour ; les chiffres gardés avec la recommandation, eux, ont les vrais noms. Le message
  porte un titre, un texte, son « Pourquoi ? » et une consigne à l'impératif (l'« Action » du rapport) ; le
  fournisseur utilisé est journalisé et gardé avec la recommandation. Seul `FakeAiProvider` existe pour l'instant :
  des gabarits en français, déterministes, qui servent aussi de repli sans IA. La personnalité (directe par défaut,
  encourageante, neutre) ne change que le ton du texte, jamais une règle, et chaque ton dit quoi faire. Le fournisseur
  est appelé dans la transaction de l'analyse : sans conséquence pour des gabarits, mais un vrai fournisseur (lent,
  réseau) formulera **après la validation** (la recommandation gardée avec le texte des gabarits, reformulée à la
  réception de `RecommendationMade`), pour ne pas tenir une transaction ouverte pendant un appel réseau.

**Conséquences.** Le noyau partagé porte quatre contrats de plus, un par besoin réel du Coach ; les contextes restent
indépendants et chacun teste son implémentation. Les seuils sont des constantes nommées des règles, faciles à revoir.
Un vrai fournisseur (Claude, OpenAI) se branchera sur le même port sans toucher aux règles. Les recommandations déjà
faites gardent leur texte : l'historique montre ce qui a été dit.

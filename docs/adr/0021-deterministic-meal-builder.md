# 0021 — Meal Builder déterministe

**Contexte.** Le Meal Builder (spec §28) propose des repas qui remplissent ce qui reste des objectifs, ajoutables d'un geste.
« Symfony calcule, l'IA formule » : la proposition doit être reproductible, testable et explicable, sans appel externe.

**Décision.**

- Objectif : le reste du jour, ou la part d'un repas (répartition × objectif, moins ce qui y est déjà saisi) ; un repas à 0 % prend le reste du jour.
  Sans objectif en vigueur, pas de proposition.
- Candidats : les 20 aliments récents de l'utilisateur, ses aliments personnels, puis quelques **basiques CIQUAL** (poulet cuit, riz cuit,
  pâtes cuites, œuf, lait demi-écrémé, banane, huile) pour qu'un nouvel utilisateur ait aussi des propositions. Pas de recettes (V2 possible).
- Chaque aliment reçoit un **rôle** selon l'origine de ses calories ; des gabarits (protéine + glucide, + lipide, + accompagnement…) sont
  essayés avec 6 candidats au plus par rôle. Les grammes sont ajustés par **moindres carrés bornés** (descente par coordonnées) sur
  protéines, glucides, lipides exprimés en kcal, dans des fourchettes réalistes par rôle, puis arrondis à la balance de cuisine.
- Classement par écart (en kcal) ; propositions dépassant 110 % des calories restantes écartées ; au plus 3, d'aliments principaux différents.
- Ajout au journal par `POST /api/nutrition/entries/batch` (une transaction : tout ou rien).

**Conséquences.** Résultat identique pour les mêmes données, testé sur l'exemple du cahier des charges. Qualité liée aux habitudes saisies :
plus l'utilisateur saisit, plus les propositions lui ressemblent. Le coach (phase 6) pourra s'en servir pour ses suggestions horaires (§29).

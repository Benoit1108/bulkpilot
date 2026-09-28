# 0020 — Journal en instantanés, objectifs versionnés, jour local

**Contexte.** « Pas d'histoire réécrite » : corriger un aliment, recevoir une nouvelle version CIQUAL ou changer d'objectif ne doit pas
modifier ce qui a été mangé ni la façon dont un jour passé est jugé. Le serveur ne connaît pas le fuseau de l'utilisateur.

**Décision.**

- Une `JournalEntry` copie le nom, l'état et les valeurs pour 100 g de l'aliment ou de la recette ; ses apports = valeurs × quantité.
  L'identifiant d'origine (aliment ou recette) sert aux « récents » et à la création de repas favoris, jamais au calcul.
- Un `NutritionTarget` s'applique à partir d'un jour et n'est jamais modifié ; l'objectif d'un jour = le **dernier fixé** parmi ceux
  effectifs ce jour-là (une décision plus récente l'emporte sur une plus ancienne, même prévue pour plus tard ; les UUID v7 départagent
  deux objectifs fixés dans la même seconde). Un nouvel objectif commence aujourd'hui ou demain, jamais dans le passé — la règle est portée
  par l'agrégat, avec un jour de marge avant le « aujourd'hui » du serveur pour les fuseaux en retard sur lui.
- Les valeurs stockées en JSON (estimation, portions, ingrédients, éléments de favoris) sont **reconstituées sans revalidation** :
  durcir une règle plus tard ne rend pas l'historique illisible.
- Les jours sont des dates **locales** `YYYY-MM-DD` choisies par la PWA ; le serveur vérifie seulement leur plausibilité
  (au plus demain, au plus 10 ans en arrière).
- Chaque écriture du journal renvoie le jour modifié (la suppression répond `204` : la PWA recharge le jour) : la PWA redessine
  l'écran avec les chiffres du serveur, sans additionner elle-même, et recharge les moyennes affichées à côté.

**Conséquences.** Un historique exact et explicable, au prix d'un peu de duplication (quelques flottants par entrée). Le coach (phase 6)
pourra créer des objectifs de source `coach`, validés par l'utilisateur, sans toucher au passé.

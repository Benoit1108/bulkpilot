# 0026 — Graphiques en SVG maison

**Contexte.** La phase 5 affiche des courbes (spec §51–59, §148) : poids du jour et moyennes 7 / 30 jours, objectif et
jalons, mensurations dans le temps, calories face au poids. Des séries temporelles simples, quelques centaines de points au
plus (une pesée par jour pendant des années reste sous 2 000). Les bibliothèques courantes (Chart.js ~70 ko, ECharts
~300 ko gzip) dessinent sur un `<canvas>` : invisible pour un lecteur d'écran, thème à recopier en JavaScript.

**Décision.**

- **Composants SVG maison** (`UiLineChart` et variantes), sans bibliothèque de graphiques : axes, lignes, zone d'objectif,
  points, info-bulle au pointeur et au clavier. Les couleurs viennent des tokens (thèmes sombre et clair gratuits).
- **Calculs hors du graphique** : moyennes, tendances et vitesses viennent de l'API (principe §2.4) ; le front ne fait que
  mettre à l'échelle (graduations « rondes », dates) dans des fonctions pures testées.
- **Accessibilité** : chaque graphique a un titre, un résumé en une phrase (« 48,2 kg, +0,4 kg en 7 jours ») et un tableau des
  valeurs dépliable ; l'information n'est jamais portée par la seule couleur (tracés continu / pointillé, marqueurs).
- **Calories face au poids** : deux graphiques alignés sur le même axe du temps plutôt qu'un double axe vertical, trompeur
  et illisible sur téléphone.

**Conséquences.** Quelques ko au lieu de dizaines, un rendu identique au reste de l'interface, testable avec Vitest. Si un
besoin dépasse les courbes (cartes de chaleur, graphiques empilés riches), une bibliothèque sera chargée à la demande sur
l'écran concerné, par un nouvel ADR.

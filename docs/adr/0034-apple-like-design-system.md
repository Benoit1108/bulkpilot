# 0034 — Charte graphique à la manière d'Apple Santé, refonte par phases

**Contexte.** Après la mise en production, le propriétaire a relevé une interface trop textuelle : peu de graphiques
et de widgets, des alignements cassés, des dates saisies au clavier, un écran de PC laissé vide aux deux tiers
(colonne de 640 px), un seul violet pour tout. Il a demandé de s'inspirer du style des applications Apple, d'opengym
pour l'entraînement et de Yazio pour la nutrition. Un audit en captures et un banc d'essai d'applications existantes
ont précédé le plan, validé sur des maquettes Claude Design.

**Décision.**

- **Charte** ([DESIGN.md](../DESIGN.md)) : gris système d'iOS, violet BulkPilot conservé pour les actions, une couleur
  fixe et neutre par nutriment, SF Pro sur les appareils Apple et Inter ailleurs, arrondis emboîtés, boutons en pilule,
  cartes sans bordure ni ombre, contrôle segmenté, listes groupées.
- **Coque** : contenu jusqu'à 1 280 px ; barre latérale à icônes à partir de xl, la section ouverte listant ses pages ;
  grand titre de page ; les pages faites de cartes indépendantes passent sur deux colonnes à partir de xl (`UiPage`).
- **Thème** : il suit le réglage de l'appareil tant que l'utilisateur n'en a pas choisi un (auparavant : sombre).
- **Illustrations d'exercices** : dessins Everkinetic (CC BY-SA 4.0), images wger sous licence CC pour les exercices
  absents, sinon l'icône du muscle principal ; carte musculaire d'après les tracés MuscleMap (MIT) ; tout est
  auto-hébergé avec une page Crédits. Les GIF d'opengym (© Gym visual) et les photos de free-exercise-db (origine
  incertaine) sont exclus.
- **Par phases**, chacune revue par les huit relecteurs et validée par `make ci` : R0 corrections, R1 charte et coque,
  R2 accueil (tuiles personnalisables), R3 entraînement (images, minuteur de repos automatique avec son et vibration),
  R4 nutrition, R5 progression, R6 Coach, notifications, réglages et thème clair. Les composants de données sont créés
  dans la phase qui les utilise en premier.

**Conséquences.** Les anciennes couleurs et la colonne de 640 px partout disparaissent ; les pastilles à coche
(`PILL`) ne restent que sur les photos, remplacées quand la page sera refaite (R5) ; les pages non encore refaites
gardent leur structure dans la nouvelle charte. Un utilisateur qui n'avait rien choisi voit désormais le thème de son
appareil ; un choix « Sombre » antérieur n'était pas enregistré (c'était la valeur par défaut) et suit donc aussi
l'appareil, à rechoisir une fois. Le manifeste garde le fond sombre (écran de lancement) : il ne peut pas suivre le
thème de l'appareil.

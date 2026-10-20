# Charte graphique

La référence visuelle de l'application. Les valeurs vivent dans `pwa/app/assets/css/tokens.css` (thème Tailwind) ;
ce document dit à quoi elles servent. Les maquettes validées sont sur le canevas Claude Design « BulkPilot — Refonte
UX/UI » (lien privé du propriétaire). Décision : [ADR-0034](adr/0034-apple-like-design-system.md).

## Principes

- **La couleur parle de données.** Kcal, protéines, glucides et lipides ont chacun leur teinte, la même partout.
  Le reste de l'interface est en gris système ; le violet est la couleur des actions.
- **Un chiffre-clé par tuile.** Grand chiffre, variation, mini-courbe ; le détail s'ouvre au toucher.
- **Listes groupées** comme Réglages iOS : une ligne par élément, des séparateurs fins, pas de cartes vides étirées.
- **Aucune morale.** Dépasser n'est jamais rouge : la barre des calories passe en gris neutre avec sa valeur, un anneau
  ou une macro reste fermé dans sa couleur ; pas de badges ni de confettis.
- **Jamais la couleur seule** : un état choisi a aussi une graisse, un contour ou un texte.

## Couleurs

| Rôle | Token | Sombre | Clair |
| --- | --- | --- | --- |
| Fond de page | `canvas` | `#000000` | `#f2f2f7` |
| Carte | `surface` | `#1c1c1e` | `#ffffff` |
| Champ, piste, bouton secondaire | `surface-raised` | `#2c2c2e` | `#f2f2f7` |
| Séparateur | `line` | `#38383a` | `#c6c6c8` |
| Texte / texte secondaire | `ink` / `muted` | `#ffffff` / `#98989f` | `#000000` / `#6c6c70` |
| Action (fond plein, son texte, survol) | `accent-fill` / `accent-contrast` / `accent-fill-hover` | `#bf5af2` / noir / `#ca7cf5` | `#8944ab` / blanc / `#7a3b9a` |
| Texte d'action | `accent-ink` | `#ca7cf5` | `#8944ab` |
| Traits, anneaux, icônes de la barre latérale | `accent` | `#bf5af2` | `#8944ab` |
| Teinte de l'élément courant (page, segment survolé) | `accent-soft` | violet à 18 % | violet à 12 % |
| Contrôle segmenté : piste, segment choisi, son contour | `track` / `thumb` / `thumb-edge` | `#2c2c2e` / `#636366` / `#8e8e93` | `#e3e3e8` / blanc / `#6c6c70` |
| Protéines / glucides / lipides | `protein` / `carbs` / `fat` | `#64d2ff` / `#ffb340` / `#ff6482` | `#0071a4` / `#b25000` / `#c1264a` |
| Atteint / attention / erreur | `success` / `warning` / `danger` | `#30d158` / `#ff9f0a` / `#ff6961` | `#1e7a34` / `#c93400` / `#d70015` |

Tout texte coloré atteint 4,5:1 sur `canvas`, `surface` et `surface-raised` ; vérifier tout ajout avec la même règle.
Le thème suit le réglage de l'appareil tant que l'utilisateur n'en a pas choisi un (Réglages › Apparence).

## Typographie

SF Pro sur les appareils Apple (`-apple-system`), Inter ailleurs. Chiffres à chasse fixe (`tabular-nums`) dès qu'ils
s'alignent. Grand titre de page 34 px gras (`text-large-title`), chiffre-clé 40 px (`text-figure`), titre de carte
18 px semi-gras, entrée de barre latérale 15 px (`text-callout`), texte 16 px, texte secondaire 14 px, libellé de
segment 13 px (`text-footnote`), légende 12 px. Pas de petites capitales grises pour les titres.

## Formes et espacements

Grille de 8 px. Arrondis emboîtés : 6 px (`sm`, puce), 10 px (`md`, champ, ligne), 12 px (`lg`, carte), 20 px (`xl`,
panneau, fenêtre), pilule (`full`). Aucune ombre sur les cartes : elles se détachent par le contraste du fond. Le flou
est réservé aux barres de navigation. Toute cible tactile fait au moins 44 px.

## Composants

- **Boutons** : tous en pilule. Plein violet (`primary`) pour l'action principale de l'écran, une seule ; teinté
  (`secondary`) pour l'action d'à côté ; texte seul (`ghost`) pour le reste ; `danger` pour une suppression armée.
- **Contrôle segmenté** (`UiSegmented`) pour choisir parmi quelques options (période, thème, vue) : segment choisi
  surélevé, contour à 3:1 et libellé plus gras.
- **Carte** (`UiCard`) sans bordure, titre en texte normal semi-gras. **Liste groupée** (`UiNavList`).
- **Panneau** (`UiDrawer`) : depuis le bas sur téléphone, sur le côté à partir de md.
- **Page** (`UiPage`) : `reading` (une colonne lisible, formulaires et listes), `columns` (cartes indépendantes,
  deux colonnes à partir de xl) ou `wide` (une carte qui se met elle-même en colonnes, les jours d'un programme) ; ce qui concerne toute la page (lien retour, période de tous les graphiques) va dans
  l'emplacement `top`, au-dessus des colonnes, et une action reste dans le même bloc que la carte qu'elle concerne.
- **Anneaux** (`UiRing`) : calories dehors, puis protéines, glucides, lipides ; fermés à l'objectif et au-delà, dans
  leur couleur ; décoratifs à côté d'un texte qui dit les chiffres, sinon une image nommée par eux. **Barres de
  macros** (`MacroBars`) : « 98 / 150 g », la barre le lit en entier ; sur l'accueil et dans le bilan du journal.
- **Tuile** (widget de l'accueil) : son nom en violet (15 px, `text-callout`) est le lien, étendu à toute la carte ; un
  chiffre seul (24 px, `text-2xl` ; 40 px pour les calories) tient en tuile — moitié d'un téléphone, quart d'un grand
  écran —, une liste ou un bouton prend une carte large. Les tuiles vont par deux dans l'ordre choisi par
  l'utilisateur ; une tuile seule s'élargit (pas de trou, ordre de lecture intact). Mini-courbe (`UiSparkline`) ou
  petites barres (`UiMiniBars`, semaines terminées, à partir de trois) sous le chiffre, décoratives.
- **Calendrier du mois** (`UiMonthCalendar`) : lundi en premier, les jours marqués remplis en violet, aujourd'hui
  cerclé ; un tableau, chaque jour lu avec ce qu'il porte ; pas de mois futur.
- **Illustration d'exercice** (`ExerciseIllustration`) : les deux temps du dessin Everkinetic (départ, effort) peints
  dans la couleur du thème par un masque CSS, ou l'image wger, sinon la silhouette des muscles (`MuscleMap`, d'après
  react-body-highlighter, MIT) ; crédit sous l'image, liste complète sur la page Crédits. En vignette, l'effort seul,
  décoratif. Les fichiers sont dans `pwa/public/exercises/` (provenance : `pwa/public/exercises/ATTRIBUTION.md` ; script
  `pwa/scripts/exercise-illustrations/fetch.mjs`), gardés en cache une fois vus, pas installés d'avance.
- **Bande de semaine** (`UiWeekStrip`) : les sept jours de la semaine du jour affiché, lundi en premier, un point sous
  ceux qui ont des repas, le jour affiché plein, aujourd'hui en violet ; un jour à venir reste focalisable mais ne
  s'ouvre pas. Seule exception à la cible de 44 px : sur les téléphones les plus étroits, une colonne fait un peu moins
  de 44 px de large (les flèches à côté donnent les mêmes déplacements en taille normale).
- **Semaine en barres** (`WeekIntake`) : une barre de calories par jour face au pointillé de l'objectif, le jour en
  cours plus clair ; un tableau masqué le dit en mots (« à venir », « en cours »). Les moyennes restent celles de la
  carte Moyennes, calculées par l'API.
- **Repas** (journal) : une liste groupée par repas, un aliment par ligne (nom, quantité, kcal), un « + » rond par repas
  et une barre fine de sa part de la journée. Sur PC, les repas à gauche, le bilan et la semaine à droite.

## Navigation

Téléphone : barre du bas (cinq sections), réglages dans l'en-tête, grand titre sous les boutons. À partir de md : rail
d'icônes. À partir de xl : barre latérale de 240 px, icônes violettes, la section ouverte liste ses pages ; la page
courante est teintée, la section porte `aria-current="true"` et sa page `aria-current="page"`. Le contenu va jusqu'à
1 280 px.

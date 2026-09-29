# 0025 — Tailwind CSS, composants maison et garde-fous du front

**Contexte.** Après la phase 4, le front comptait 92 composants et environ 2 900 lignes de CSS dans des blocs `<style scoped>`
(un quart du code Vue), souvent les mêmes règles de mise en page réécrites ; 17 fichiers dépassaient 200 lignes, dont deux
au-delà de 550. La phase 5 ajoute beaucoup d'écrans (pesées, mensurations, photos, graphiques). Question posée : Tailwind,
une bibliothèque de composants (Nuxt UI), une taille maximale de composant ?

**Décision.**

- **Tailwind CSS v4** (`@tailwindcss/vite`) pour tout le style, **sans son preflight** : la base reste celle de `main.css`
  (valeurs par défaut du navigateur conservées, bordures à 0 comme Tailwind l'attend). Le **thème Tailwind est nos tokens**
  (`tokens.css`, `@theme static`) : couleurs, rayons, tailles de texte, cible tactile ; le thème clair ne redéfinit que les
  couleurs. Les valeurs par défaut de Tailwind (palette, polices) sont retirées : seule une valeur du thème est utilisable.
- **Pas de Nuxt UI** : nos composants de base (`components/ui`) sont petits, accessibles (AA vérifié par axe en E2E) et faits
  pour nos besoins (focus, annonces, typographie française) ; Nuxt UI imposerait son style et son API, avec des versions
  majeures incompatibles entre elles. Quand un composant complexe sera nécessaire (liste de choix avec saisie, sélecteur de
  date, onglets), il sera construit sur **Reka UI** (composants sans style, accessibles), la base de Nuxt UI.
- **Garde-fous automatiques** : ESLint `better-tailwindcss` (classe inconnue ou en conflit refusée, ordre des classes imposé),
  `max-lines` à 250 lignes par composant (au-delà : composable ou sous-composant), budget de taille de l'application vérifié
  à chaque build de l'image de production (`bundle-budget.json`).

**Conséquences.** Plus de CSS propre à écrire pour un nouvel écran ; les templates sont plus chargés, compensés par les
composants de base qui portent les variantes (`UiButton`, `UiLinkButton`, `components/ui/classes.ts`). Les tests ne
s'appuient plus sur des classes CSS (rôles et noms accessibles, `data-testid` sinon). Le budget se relève délibérément,
en le justifiant, jamais pour faire passer un build.

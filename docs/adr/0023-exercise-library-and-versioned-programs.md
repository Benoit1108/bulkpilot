# 0023 — Bibliothèque d'exercices et programmes versionnés

**Contexte.** Spec §32–49 : exercices (muscles, équipement, unité, plage de reps, RIR, repos, tempo, consignes), programmes
qui coexistent dont un seul est actif, et versions : « une modification majeure crée une nouvelle version, ne jamais réécrire
l'histoire des entraînements ». Il faut des exercices dès la première séance, sans tout saisir.

**Décision.**

- **Bibliothèque commune** d'environ 90 exercices courants en français, livrée avec le code (`Training/Infrastructure/Library/exercises.json`,
  clés stables) et importée par `training:library:import`, idempotente, lancée à chaque démarrage du conteneur. En lecture seule ;
  un exercice retiré du fichier reste en base (programmes et historique y font référence). S'y ajoutent les **exercices perso**
  de chaque utilisateur (modifiables, suppression douce : ils restent nommés là où ils servent, mais ne s'ajoutent plus ailleurs).
- **Consigne personnelle** par utilisateur et par exercice (`exercise_notes`), bibliothèque comprise : affichée à chaque fois qu'il le fait.
- **Mesure** d'une série par exercice : charge × reps, poids du corps (lest facultatif) × reps, ou durée.
- **Programme** = nom + pointeur vers sa **version courante**. Une **version** (`ProgramVersion`, agrégat immuable) porte les jours →
  blocs → exercices prescrits (séries, fourchette, RIR, repos, tempo, stratégie de progression, palier, échauffements, drop set,
  consigne propre au programme). Un bloc de plusieurs exercices est un **superset** (A1, A2).
- **Quand versionner** : renommer ne crée pas de version ; toute différence dans les jours (champ par champ) crée la version
  suivante. Règle simple et sûre : une séance passée pointera toujours sur une version inchangée.
- **Un seul programme actif** par utilisateur : règle applicative (activer l'un désactive l'autre) et index unique partiel
  `training_programs_one_active`. Un programme créé alors qu'aucun n'est suivi devient le programme suivi. Archiver un programme le
  désactive, ses versions restent.
- **Bornes** par utilisateur : 300 exercices perso, 50 programmes, 500 versions par programme (au-delà : 422, l'utilisateur crée un
  nouveau programme).

**Conséquences.** Modifier souvent un programme multiplie les versions (quelques lignes JSON chacune) : acceptable. Les versions
se consultent (`GET /programs/{id}?version=N`). Le moteur de progression (stratégie par exercice prescrit) et les séances viennent ensuite.

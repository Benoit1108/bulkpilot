# 0024 — Séances hors ligne, écritures idempotentes, progression et records côté serveur

**Contexte.** Spec §42–47 et §116–118 : en salle, la connexion tombe ; une série ne doit jamais être perdue, la séance doit
survivre à la fermeture de l'application, et la reprise doit proposer « Reprendre / Terminer ». La progression (suggestion de
charge) et les records sont des règles métier : ils ne se calculent pas dans la PWA (principe §2.4).

**Décision.**

- **Identifiants choisis par l'appareil** (UUID) pour la séance et chaque série ; toutes les écritures sont **idempotentes** :
  `PUT /workouts/{id}` (démarrer), `PUT /workouts/{id}/exercises/{index}` (ajouter), `PUT /workouts/{id}/sets/{setId}` (enregistrer
  ou corriger), `DELETE …/sets/{setId}`, `POST /workouts/{id}/finish` (la première heure de fin est gardée), `DELETE /workouts/{id}` (abandonner
  deux fois répond 204 ; une séance inconnue, 404, que la PWA compte comme faite). Renvoyer une écriture après une réponse perdue ne change rien ; un id déjà
  abandonné ou appartenant à un autre compte ne redémarre pas (422).
- **Horaires de l'appareil** : `startedAt` sur les 30 derniers jours (jusqu'à +10 min d'avance) ; une série entre 5 min avant le
  début et la fin (ou début + 1 jour) ; une séance dure au plus un jour. Au plus 80 exercices par séance. Écritures limitées à
  `RATE_LIMIT_TRAINING_WRITES` (600) par minute et par compte.
- **Instantané du plan** : une séance copie les exercices du jour **de la version vue par l'appareil** (`versionNumber`), avec leur
  prescription ; changer le programme ou l'exercice ensuite ne la modifie pas.
- **Une seule séance en cours** par utilisateur : règle applicative (409 `training.workout_in_progress`) et index unique partiel.
- **PWA** : la séance en cours vit dans IndexedDB (`services/offline/store.ts`, mémoire en repli), chaque changement y est écrit
  puis mis dans une **file d'envoi** ordonnée (`features/training/outbox.ts`), vidée tout de suite, au retour du réseau, au retour
  sur l'application et par nouvelles tentatives espacées. Réseau, 5xx, 429, session expirée et `shared.concurrent_update` → on
  réessaie ; toute autre erreur → l'envoi est **gardé en échec**, visible, avec « Réessayer » ou « Les ignorer » (les envois de la
  même séance qui en dépendent sont ignorés avec lui). La valeur de la séance et son envoi sont écrits dans **une même transaction**
  IndexedDB : rien n'est gardé sans son envoi, et inversement.
  Les plans des jours du programme suivi (clé : programme + version) et le programme lui-même sont gardés à l'avance
  (`GET /workouts/preview`) pour démarrer sans réseau, y compris à froid.
- **Appareil lié à un compte** (`services/offline/owner.ts`) : les données hors ligne appartiennent au compte qui les a écrites. Un
  autre compte qui se connecte sur l'appareil les efface d'abord ; la déconnexion volontaire les efface ; une session expirée les
  garde (« Session expirée », envoyées à la reconnexion du même compte). La file ne se vide que pour le compte propriétaire.
- **Règles de déroulé côté appareil** (exception assumée au principe §2.4, car elles doivent marcher hors ligne) : quel repos lancer
  (aucun au sein d'un superset, celui du tour après le dernier ; échauffements ≤ 90 s ; drop set prévu enchaîné sans repos), type
  de série suivant, valeurs de départ (échauffement à mi-charge, drop set un quart plus léger), prescription habituelle d'un
  exercice ajouté (3 séries, palier 2,5 kg, comme `PlannedExercise::usual`). Elles ne décident rien de durable : la progression et
  les records restent calculés par l'API.
- **Progression et records côté serveur** (`ProgressionAdvisor`, `RecordBook`, domaine) : la suggestion suit la stratégie de
  l'exercice prescrit (double progression, linéaire, charge fixe, reps fixes, RIR cible) à partir des séries de travail de la
  dernière fois (les séries les plus lourdes, autant que de séries prévues) ; les records (charge max, 1RM estimé Epley ≤ 12 reps, volume de séance, reps au poids du corps, durée, reps à une
  charge) ne comptent que les séries de travail, et la première fois n'en est pas un. La PWA les affiche tels que l'API les donne
  (après chaque série reçue) : hors ligne, ils arrivent au retour du réseau.

**Conséquences.** Deux appareils sur la même séance : le verrou optimiste refuse l'écriture simultanée (409, réessayée), la dernière
série enregistrée gagne (last-write-wins, §118). La séance sur l'appareil fait foi pendant l'entraînement ; celle d'un autre appareil
se reprend explicitement. Une séance terminée hors ligne apparaît dans l'historique une fois envoyée.

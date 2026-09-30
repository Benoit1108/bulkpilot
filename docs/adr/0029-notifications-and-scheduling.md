# 0029 — Notifications dans l'application, tâches planifiées et worker

**Contexte.** Spec §75–78, §100 : des notifications importantes seulement, par catégories activables (calories très
insuffisantes, séance prévue non commencée, pesée manquante, objectif atteint, PR, rapport hebdomadaire), des horaires
silencieux configurables (23:00 → 08:00), rien de redondant (« tu n'as pas encore dîné » juste après la saisie du dîner) ;
le push n'est pas obligatoire au premier MVP mais l'architecture doit l'accueillir, et les recommandations restent visibles
dans l'application. Le Coach (ADR-0028) ne tournait qu'à l'ouverture de sa page ; une analyse nocturne rend le rapport
de la semaine prêt au réveil. Il faut donc du travail planifié, dans le fuseau de chaque utilisateur.

**Décision.**

- **Contexte `Notification`**, qui ne dépend que du noyau partagé. Il lit les autres contextes par des ports de
  `Shared\Application\Notifying`, un par propriétaire des données, qui ne renvoient que des chiffres :
  `EatingReadings` (Nutrition : mangé du jour, cible, dernière saisie), `WeighingReadings` (Progression : pesée du jour,
  pesées des 7 jours d'avant, étapes de l'objectif atteintes), `TrainingActivity` (Training : séances de la semaine,
  séances prévues par le programme actif, séance commencée aujourd'hui, séances avec records finies depuis 48 h),
  `CoachReports` (Coach : semaine du dernier rapport). Le fuseau vient du port `Shared\Application\Accounts\Accounts`
  (Identity : chaque compte et son fuseau IANA, envoyé par l'appareil ; `clockOf` pour un seul compte, et un fuseau que
  la base des fuseaux ne connaît plus donne celui par défaut : le travail planifié ne s'arrête jamais sur lui).
- **Règles déterministes** en Domain, dans l'heure locale de l'utilisateur, chacune avec une **clé** qui dit de quoi elle
  parle (`low_calories:2026-09-30`, `record:<séance>`, `goal_reached:<objectif>:<kg>`…) : **une notification par clé et
  par utilisateur, pour toujours** (contrainte unique en base) — c'est la déduplication contextuelle du §77 ; la règle des
  calories se tait aussi dans l'heure qui suit une saisie. Rien n'est lu ni créé pendant les horaires silencieux (s'il y
  en a : l'utilisateur peut ne pas en vouloir) ni quand toutes les catégories sont désactivées.
- **Tour de vérification atomique**, à part des réglages que l'utilisateur modifie : la table `notification_checks`
  garde l'heure de la dernière vérification de chaque compte, prise par une seule instruction
  (`INSERT … ON CONFLICT … DO UPDATE … WHERE checked_at <= maintenant − 5 min`) avant toute lecture. De deux
  vérifications simultanées (le programme et l'ouverture de l'app), la seconde attend la fin de la première puis ne fait
  rien : jamais de 409 sur `POST /api/notifications/check`, et jamais de conflit avec un enregistrement des réglages.
- **Dans l'application seulement** : une cloche et une liste (lue / non lue), l'ouverture de l'app lance la vérification
  (`POST /api/notifications/check`, synchrone, pour que la liste lue ensuite la contienne). Le **push** est un port
  (`PushSender`) implémenté par `NullPushSender` : une notification créée lève `NotificationCreated`, livré **après la
  validation** de la transaction (ADR-0014) et routé vers le worker, dont l'abonné appelle le port. Une notification
  annulée avec sa transaction n'est donc jamais poussée ; une livraison réessayée peut pousser deux fois : un envoi Web
  Push (VAPID, abonnements par appareil) dédoublonnera sur l'identifiant de la notification.
- **Symfony Scheduler + worker Messenger.** Deux programmes, chacun à son contexte : `notifications` (toutes les 15 min,
  `RunScheduledChecks` envoie un `CheckAccount` par compte sur le transport `async`) et `coach` (toutes les 15 min,
  `RunNightlyAnalyses` envoie un `AnalyseAccount` aux comptes dans le premier quart d'heure de 3 h ou de 4 h, heure
  locale : les deux, parce qu'une nuit de passage à l'heure d'été saute 3 h dans certains fuseaux (Helsinki passe de
  2 h 59 à 4 h) ; l'analyse ne tourne qu'une fois par jour, le second envoi ne change rien). Les programmes sont en
  Presentation (ils pilotent l'application comme une commande console). Un message par compte : un compte en échec est
  réessayé seul (10 s, 30 s, 90 s, puis file `failed`), et un compte qui échoue pendant la répartition n'arrête pas les
  autres. Les programmes sont `stateful` (un seul passage rattrapé après un arrêt) avec un cache et un verrou **locaux au
  conteneur** (cache fichiers, `flock`) : **un seul worker** (docs/DEPLOYMENT.md). Le service `worker` (même image,
  mêmes variables, sans serveur web) consomme `async`, `scheduler_notifications` et `scheduler_coach` ; `php` passe les
  migrations, le worker attend qu'il soit sain et s'arrête toutes les heures pour repartir propre.
- **Textes en français** composés par les règles avec l'aide partagée `Shared\Domain\Text\French` (la même que le
  Coach : « 2 350 kcal », signe moins, espaces insécables devant « : ; ! ? % ») : pas d'IA ici, les notifications sont
  des constats courts.

**Conséquences.** Le noyau partagé gagne quatre petits contrats de lecture de plus et celui des comptes. Une
notification manquée pendant les horaires silencieux n'est pas rattrapée ensuite si sa règle ne tient plus (la
séance faite, le repas saisi) — c'est voulu. Sans worker (pile E2E), seules les vérifications à l'ouverture tournent. La
liste des comptes est lue en entier à chaque passage du programme (une vérification à l'ouverture ne lit que le compte) :
suffisant pour une application personnelle, à paginer au-delà de quelques milliers de comptes. Pour faire tourner
plusieurs workers, il faudra un verrou et un état partagés (verrou consultatif PostgreSQL, cache en base).

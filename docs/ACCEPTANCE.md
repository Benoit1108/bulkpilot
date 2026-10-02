# Critères d'acceptation

Chaque fonctionnalité importante a ses critères (spec §112), et chaque critère au moins un test qui le
vérifie. Les références sont de la forme `fichier::test` :

- `api/tests/Api/…` : tests API (PHPUnit, base réelle, requêtes HTTP) ;
- `api/tests/Unit/…` : règles métier sans framework ;
- `pwa/test/nuxt/…` : composants et pages (Vitest), le nom du test entre guillemets ;
- `pwa/e2e/…` : parcours dans un navigateur sur l'image de production (Playwright), le titre du test.

Règle commune à tout ce qui suit, vérifiée une fois pour toutes les routes :
**aucun autre utilisateur ne peut voir ni modifier la donnée** —
`Api/Security/AccessMatrixTest.php::testAnotherUsersResourcesAreNotFoundAndLeftAlone` (chaque route de
l'OpenAPI, avec les identifiants d'un autre compte : 404, rien modifié) et
`::testSignedOutEveryOperationButThePublicOnesIsRefused` (sans connexion : 401). Une route ajoutée est couverte
d'office ; `::testEveryOperationSaysWhoMayCallIt` échoue si elle ne dit pas qui peut l'appeler.

## Compte et sécurité

### Créer son compte
- l'inscription connecte l'appareil et envoie un lien de vérification —
  `Api/Identity/RegistrationAndSignInTest.php::testRegistrationSignsTheDeviceInAndEmailsAVerificationLink`,
  e2e `identity.spec.ts` « sign up, confirm the email from the inbox, and be greeted » ;
- une adresse ne s'inscrit qu'une fois, les champs invalides sont refusés un par un —
  `::testAnEmailCanOnlyBeRegisteredOnce`, `::testInvalidRegistrationsAreRejectedFieldByField` ;
- l'inscription peut être fermée — `::testRegistrationCanBeClosed`.

### Se connecter
- mauvais mot de passe et adresse inconnue répondent pareil, les échecs répétés sont freinés —
  `::testWrongPasswordAndUnknownEmailAnswerTheSame`, `::testRepeatedFailuresOnOneAccountAreThrottled` ;
- la double authentification (application, codes de secours) s'applique, aussi après Google et une réinitialisation —
  `Api/Identity/TwoFactorTest.php::testEnablingThenSigningInWithAnAuthenticatorCode`, `::testARecoveryCodeSignsInOnce`,
  `Api/Identity/ExternalSignInTest.php::testTheSecondFactorStillApplies`,
  `Api/Identity/HardeningTest.php::testTwoFactorStillAppliesAfterAPasswordReset`, e2e `two-factor.spec.ts` ;
- Google : compte créé, retour à la page demandée, jamais de redirection vers un autre site —
  `Api/Identity/ExternalSignInTest.php::testSigningInCreatesTheAccountAndReturnsToTheRequestedPage`, `::testExternalRedirectsAreNeverFollowed`.

### Mot de passe, appareils, profil
- un mot de passe oublié se réinitialise par le lien envoyé, et déconnecte les autres appareils —
  `Api/Identity/EmailAndPasswordTest.php::testAPasswordResetSignsEveryDeviceOut`, e2e « a forgotten password is reset from the emailed link » ;
- les appareils sont listés et déconnectables à distance —
  `Api/Identity/ProfileAndDevicesTest.php::testDevicesAreListedAndCanBeSignedOutRemotely`, e2e « another device can be signed out remotely » ;
- le profil est enregistré et relu ; un profil invraisemblable est refusé —
  `::testTheProfileIsReplacedAndReturned`, `::testImplausibleProfilesAreRejected`, e2e « the profile is saved and shown again after a reload ».

### Protection
- une requête modifiante venue d'un autre site est refusée — `Api/Security/AccessMatrixTest.php::testAWriteSentFromAnotherSiteIsRefusedBeforeAnythingRuns` ;
- chaque budget dépensé répond 429 avec le délai — `Api/Security/RateLimitsTest.php::testASpentBudgetAnswers429WithWhenToRetry` ;
- en-têtes de sécurité, cookie de session hors de portée des scripts, jamais de trace d'erreur — e2e `security.spec.ts` ;
- deux utilisateurs servis par le même worker PHP ne se voient jamais — `Api/Identity/WorkerModeTest.php`, e2e « users alternating on the PHP workers never see each other ».

## Nutrition

### Fixer ses objectifs
- l'estimation montre chaque étape et n'enregistre rien ; l'accepter fixe l'objectif du jour —
  `Api/Nutrition/TargetsTest.php::testTheEstimateShowsEachStepAndSavesNothing`, `::testAcceptingTheEstimateSetsTheTargetOfTheDay`,
  e2e `nutrition.spec.ts` « targets from the estimate, then a food logged against them » ;
- un objectif manuel doit tenir debout et commencer aujourd'hui ou demain — `::testManualTargetsMustBeConsistentAndStartTodayOrLater` ;
- le calcul suit Mifflin-St Jeor et sépare exactement les macros — `Unit/Nutrition/Domain/TargetTest.php::testOtherCaloriesKeepTheProteinAndFatAndMakeUpTheRestInCarbohydrates`, `Unit/Nutrition/Domain/BoundariesTest.php::testTheEstimateSplitsTheMacrosExactly` ;
- la répartition de la journée entre les repas se change et fait 100 % (§27) — `Api/Nutrition/TargetsTest.php::testTheMealSplitIsReadAndReplaced`, `Unit/Nutrition/Domain/TargetTest.php::testTheMealSplitIsChangedAndMissingSlotsAreZero`, e2e « the meal split must add up to 100 % ».

### Noter ce que l'on mange
- une saisie répond avec la journée à jour ; corriger, déplacer, retirer — `Api/Nutrition/JournalTest.php::testLoggingAnswersWithTheUpdatedDay`, `::testAnEntryIsCorrectedMovedAndRemoved`, e2e « an entry is corrected, moved to another meal, then removed » ;
- un utilisateur en avance sur le serveur saisit sur son propre jour — e2e « a user far ahead of the server in time logs on their own today » ;
- les moyennes ne comptent que les jours saisis — `Api/Nutrition/JournalTest.php::testAveragesCoverTheLoggedDays` ;
- dépasser la cible se dit sans alarme : ce qui reste, puis un dépassement neutre (§30) — `pwa/test/nuxt/nutritionDay.test.ts` « shows what is left, then a neutral overflow ».

### Aliments, code-barres, recettes, favoris, Meal Builder
- la base se cherche sans accents ni majuscules ; un aliment perso reste privé — `Api/Nutrition/FoodsTest.php::testTheCatalogueIsSearchedWithoutAccentsOrCase`, `::testCustomFoodsArePrivateToTheirOwner` ;
- cru ou cuit : l'état d'un aliment se lit dans son nom, une recette se pèse cuite (§19) — `Unit/Nutrition/Domain/FoodTest.php::testTheStateIsReadFromTheCatalogueName`, `Unit/Nutrition/Domain/RecipeTest.php::testTheCookedWeightGivesServingsAndPer100GramsOfTheCookedDish` ;
- un produit scanné est confirmé, gardé, puis saisi ; un inconnu est complété — e2e « a scanned product is confirmed, kept, then logged; an unknown one is typed in » ;
- une recette se saisit à la portion ; un repas se garde en favori et se refait d'un geste — `Api/Nutrition/RecipesAndFavoritesTest.php`, e2e « a recipe is logged by the serving… » ;
- le Meal Builder remplit ce qui reste de la journée, une proposition se saisit en une fois — `Api/Nutrition/MealBuilderTest.php::testItProposesMealsForWhatIsLeftAndOneIsLoggedInOneGo`, `Unit/Nutrition/Domain/MealBuilderTest.php::testTheSpecExampleGivesExactlyTheseGrams` ;
- la proposition va dans le repas de l'heure (§29) — `pwa/test/nuxt/mealBuilder.test.ts` « proposes meals for the rest of the day and logs one into the meal of the time of day ».

## Training

- un programme avec superset devient actif ; changer ses jours crée une version et garde l'ancienne —
  `Api/Training/ProgramsTest.php::testAProgramIsCreatedWithSupersetsAndBecomesActive`, `::testEditingTheDaysRecordsAVersionAndKeepsTheOldOne`, e2e `training.spec.ts` ;
- une séance se fait série par série puis se termine ; une seule à la fois — `Api/Training/WorkoutsTest.php::testAWorkoutIsStartedSetBySetThenFinished`, `::testOneWorkoutAtATimeAndFreeOnesGetExercisesOnTheGo` ;
- une séance hors ligne est envoyée une fois le réseau revenu, sans doublon — e2e `offline.spec.ts`, e2e « a workout set by set, through a lost connection and a reload, then in the history » ;
- deux appareils sur la même séance : le second refusé, son nouvel essai gagne (§118) — `Integration/Training/WorkoutPersistenceTest.php::testTwoDevicesWritingTheSameWorkoutAtOnceDoNotBothWinAndTheRetryDoes` ;
- une séance non terminée se reprend ou se termine (§46) — `pwa/test/nuxt/trainingHome.test.ts` « offers to resume a workout in progress, or to finish it through the confirmation » ;
- le minuteur de repos se met en pause, se change, annonce sa fin (§44) — `pwa/test/nuxt/workoutPage.test.ts` « pauses, changes and ends a rest », « counts down, announces a few steps and the end, even one that passed » ;
- le tempo se lit sous ses formes usuelles (§40) — `Unit/Training/Domain/PrescriptionTest.php::testATempoIsReadInItsUsualForms` ;
- un exercice perso se crée, se modifie, se supprime ; une note est personnelle et s'affiche avec l'exercice (§41) — `Api/Training/ExercisesTest.php::testAnOwnExerciseIsCreatedEditedAndDeleted`, `::testANoteIsPersonalAndShownWithTheExercise` ;
- la séance suivante suit le programme dans l'ordre, en boucle, même après une révision — `Api/Training/NextWorkoutTest.php` ;
- les records et la progression suggérée sont justes — `Unit/Training/Domain/ProgressionEdgesTest.php::testTheBestRepsAtTheTopLoadAreRepeated`, `::testRecordsAWorkoutBeatAreAListInTheirOrder`, `Unit/Training/Domain/ProgressionTest.php::testWarmUpsDoNotCountAndTheFirstTimeHasNoSuggestion`.

## Progression

### Ajouter une pesée (exemple de la spec §112)
- l'utilisateur peut saisir son poids, la date est enregistrée, une seconde pesée du jour remplace la première —
  `Api/Progression/WeightTest.php::testADayHasOneWeighInThatWeighingAgainReplaces`, `::testAWeighInIsAPlausibleWeightOnADayThatHasCome` ;
- le poids apparaît au tableau de bord — e2e `dashboard.spec.ts` « a new account starts with its first steps; once one is done… » (la pesée de 51,2 kg s'affiche dans le widget Poids) ;
- la moyenne est recalculée — `Api/Progression/WeightTest.php::testAWeighInIsInTheAveragesAndTheChartAtOnce` (moyennes 7 et 30 jours avant et après la pesée), `::testTheSummaryGivesTheAveragesTheSpeedAndTheChartOfThePeriod` ;
- les graphiques sont mis à jour — e2e `progression.spec.ts` « a weigh-in, the averages and the trend… » (le point et le tableau du graphique montrent 49,6 kg) ;
- aucun autre utilisateur ne peut voir la donnée — `Api/Progression/WeightTest.php::testTheWeighInsAreTheUsersOwn` et la matrice d'accès.

### Objectifs, mensurations, photos, analyses
- un objectif et ses jalons partent de la dernière pesée, avec une estimation — `Api/Progression/WeightTest.php::testAGoalWithMilestonesStartsFromTheLatestWeighIn` ;
- une journée de mensurations se remplace en entier ; la synthèse est celle de son propriétaire — `Api/Progression/MeasurementsTest.php` ;
- une photo reste privée, servie par un lien qui expire, supprimée pour de bon — `Api/Progression/PhotosTest.php`, e2e « photos kept in the bucket… » ;
- les analyses posent les calories face au poids et résument l'entraînement — `Api/Nutrition/HistoryTest.php`, `Api/Training/AnalyticsTest.php`, e2e « the analytics set calories against weight… ».

## Coach

- un poids stable avec une cible atteinte propose +150 kcal ; accepter fixe l'objectif à partir de demain —
  `Api/Coach/RecommendationsTest.php::testAStableWeightOnATargetReachedProposesMoreCaloriesThatAcceptingSetsFromTomorrow` ;
- manger bien en dessous de la cible demande de l'atteindre d'abord — `::testEatingWellBelowTheTargetAsksToReachItFirst`, `Unit/Coach/Domain/CalorieRuleTest.php` (règles sans IA, spec §111) ;
- une proposition faite sur un objectif changé depuis est refusée — `Api/Coach/RecommendationsTest.php::testAProposalMadeOnATargetChangedSinceIsRefused` ;
- « plus tard » met de côté, un refus n'est pas reproposé ; l'analyse tourne une fois par jour — `Api/Coach/RecommendationsTest.php::testPostponedIsSetAsideAndRejectedIsNotOfferedAgain`, `Api/Coach/RecommendationsTest.php::testTheAnalysisRunsOnceADayUnlessAskedAgain` ;
- la semaine finie a son rapport, une fois — `Api/Coach/ReportsAndSettingsTest.php::testTheLastFinishedWeekGetsItsReportOnce` ;
- trois recommandations au plus, la plus importante d'abord, chacune expliquée par ses chiffres (§68–69) — `Unit/Coach/Application/GetCoachTest.php::testAtMostThreeAreShownTheMostImportantFirstPostponedOnesAside`, `Unit/Coach/Domain/RulesTest.php::testTheEngineOrdersByLevelThenEatingBeforeTraining`, `Unit/Coach/Infrastructure/FakeAiProviderTest.php::testACalorieProposalIsExplainedWithItsFigures` ;
- l'adhérence se compose de parts expliquées, celles sans attente laissées de côté (§58) — `Unit/Coach/Domain/AdherenceTest.php::testEachPartIsExplainedAndTheScoreIsTheirMean`, `::testPartsWithNothingExpectedAreLeftOut` ;
- le ton ne change que ce qui est dit ensuite — `Api/Coach/ReportsAndSettingsTest.php::testTheSettingsChangeTheToneOfWhatIsSaidNextOnly`, e2e `coach.spec.ts`.

## Tableau de bord, recherche, notifications

- un nouveau compte voit ses premières étapes ; les widgets, les vues et la disposition sont ceux de l'utilisateur —
  e2e `dashboard.spec.ts`, `pwa/test/nuxt/dashboard.test.ts` « gives a new account its first steps instead of empty widgets », « reads the last 30 days in the month view » ;
- la disposition se change, s'enregistre sur le compte et ne touche jamais au compte lui-même —
  `pwa/test/nuxt/dashboardCustomize.test.ts` « shows, hides and moves widgets with labelled buttons, then saves the whole order »,
  `Api/Identity/PreferencesTest.php::testTheDashboardStartsInTheDefaultOrderThenKeepsTheUsers`, `::testPreferencesNeverChangeTheAccount` ;
- le « + » mène à chaque formulaire — `pwa/test/nuxt/shell.test.ts` « opens a panel of everything one can add, each leading to its form », e2e « leads to the weigh-in… » ;
- Ctrl+K / ⌘K trouve pages, aliments, recettes, exercices, programmes et séances — `shell.test.ts` « finds pages, recipes, exercises, programs and sessions on the device… », e2e « …and Ctrl+K finds a page or says when nothing is found » ;
- notifications :
  - calories très insuffisantes le soir, record du jour — `Api/Notification/NotificationsTest.php::testTheEveningCheckTellsTheLowCaloriesAndTheRecordOfTodaysWorkout` ;
  - pesée oubliée par quelqu'un qui se pèse d'habitude — `::testSomeoneWhoUsuallyWeighsIsRemindedLateInTheMorning` ;
  - jalon atteint — `::testAMilestoneReachedIsTold` ;
  - séance prévue non commencée, rapport hebdomadaire — `Api/Notification/NotificationsTest.php::testASessionTheWeekStillNeedsIsRemindedInTheEvening`, `::testTheWeeklyReportIsToldOnceDrawn` ;
  - le push est prêt : il ne part que pour une notification enregistrée (§78) — `Unit/Notification/Application/CheckTest.php::testTheChecksReadTheUsersDayInTheirTimeZone` ;
  - horaires silencieux et fuseau de l'utilisateur, catégories désactivées — `Api/Notification/NotificationsTest.php::testTheChecksFollowTheUsersTimeZoneAndQuietHours`, `Api/Notification/NotificationsTest.php::testCategoriesTurnedOffAreNotTold` ;
  - la cloche compte, ouvrir mène où il faut et marque lu — e2e `notifications.spec.ts` « a record beaten: the bell counts it… ».

## Données personnelles

- un export est fait par le worker et se télécharge par un lien signé ; il couvre la période et les domaines demandés ; aucun secret n'y figure —
  `Api/Export/ExportsTest.php::testAnExportIsBuiltByTheWorkerThenDownloadedThroughASignedLink`, `::testAnExportCoversThePeriodAndTheDomainsAskedFor`,
  `::testNoSecretOfTheAccountIsEverExported`, e2e `data.spec.ts` « an export is asked for, made by the worker, downloaded… » ;
- supprimer son compte déconnecte tout de suite tous les appareils ; se reconnecter dans les 7 jours l'annule ; après, tout est effacé —
  `Api/Identity/AccountDeletionTest.php::testTheAccountIsClosedAtOnceOnEveryDevice`, `::testSigningInAgainCancelsTheDeletionAndSaysSoOnce`,
  `::testAfterSevenDaysEverythingOfTheUserIsErasedAndTheEmailCanRegisterAgain`, e2e « deleting the account signs out everywhere… » ;
- l'effacement ne laisse rien de l'utilisateur, dans aucune table, et rien des autres n'est touché —
  `Api/Export/ErasureTest.php::testEveryTableIsOwnedOrKnown`, `::testAnErasedUserLeavesNothingAndTheOthersKeepEverything` ;
- l'activité du compte dit ce qui a changé et pourquoi (source : utilisateur, Coach) — `Api/Audit/ActivityTest.php::testASignInAndATargetChangedByTheCoachAreTheUsersOwn` ;
- une sauvegarde se restaure, une sauvegarde illisible est refusée — `make backup-test` (dans `make ci-prod`), `Unit/System/Application/Backup/BackupTest.php::testTheLatestBackupIsRestoredAndChecked`, `::testABackupThatDoesNotRestoreFailsTheVerification`.

## Qualité transverse

- chaque page tient à 320, 390, 768 et 1280 px — e2e `responsive.spec.ts` ;
- pas de requête en trop ni de lecture sans index sur les chemins chauds — `Api/Performance/QueryBudgetTest.php`, `Api/Performance/IndexUsageTest.php` ;
- les règles métier sont vérifiées par mutation (MSI ≥ 98 % sur les Domain, cliquet sur les cas d'usage) — `make infection` (voir TESTING.md) ;
- chaque référence de ce document est un test qui existe — `make acceptance-check` (dans `make qa`).

## Tenir ce document à jour

Une fonctionnalité ajoutée ou changée met à jour sa section **dans le même commit** : le critère en français, du point
de vue de l'utilisateur, puis le test qui le prouve. Un critère sans test est un test à écrire, pas une ligne à
retirer ; un test renommé met sa référence à jour : `tools/check_acceptance.py` (`make acceptance-check`, dans
`make qa`) échoue sur toute référence morte. La revue de fin de phase (domaine « tests ») vérifie ce document.

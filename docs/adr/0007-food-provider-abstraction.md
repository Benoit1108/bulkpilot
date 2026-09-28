# 0007 — Abstraction de la base alimentaire

**Contexte.** Le scan de code-barres s'appuie sur Open Food Facts, dont la qualité et la disponibilité varient.

**Décision.** `FoodDatabaseProviderInterface` dans le contexte Nutrition, implémentée par `FakeFoodDatabaseProvider`
et `OpenFoodFactsProvider`. Les données externes sont normalisées puis **présentées pour confirmation** avant tout ajout.
Le frontend n'appelle jamais Open Food Facts directement.

**Conséquences.** Fournisseur remplaçable, développement hors ligne, aucune donnée externe enregistrée silencieusement.

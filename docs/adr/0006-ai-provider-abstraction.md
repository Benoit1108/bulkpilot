# 0006 — Abstraction du provider IA

**Contexte.** Le Coach doit fonctionner sans IA et sans clé API en local ; le fournisseur pourra changer.

**Décision.** `AiProviderInterface::generateRecommendation(CoachAnalysis): CoachMessage` dans le contexte Coach,
avec `FakeAiProvider` (local, tests), puis `ClaudeAiProvider` / `OpenAiProvider`. L'IA reçoit une analyse
**déjà calculée** par le moteur de règles et ne produit que la formulation. Données minimales, jamais de données
d'authentification ; le provider utilisé est journalisé.

**Conséquences.** Les décisions du Coach sont déterministes et testables sans IA ; l'IA n'est jamais source de vérité.

# 0004 — PostgreSQL

**Contexte.** Données relationnelles fortement liées (journal, recettes, séances), besoin d'intégrité,
de requêtes analytiques (moyennes glissantes, tendances) et d'une offre managée en production.

**Décision.** PostgreSQL 18, en conteneur en local, managé en production. Migrations Doctrine versionnées,
exécutées au démarrage. La même base héberge la file Messenger tant que le volume ne justifie pas Redis.

**Conséquences.** Fonctions fenêtrées disponibles pour l'analytics ; Redis n'est introduit que sur besoin mesuré.

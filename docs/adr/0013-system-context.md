# 0013 — Contexte `System` pour les préoccupations opérationnelles

**Contexte.** Le health check était placé dans `Shared`. Or tout contexte peut dépendre de `Shared` :
y mettre des adapters opérationnels les rendrait importables partout et ferait grossir le noyau partagé.

**Décision.** `Shared` ne contient que le vocabulaire commun (identifiants, événements, exceptions, bus).
Les préoccupations opérationnelles (santé, et plus tard métriques ou maintenance) vivent dans `App\System`,
un contexte non métier soumis aux mêmes règles de couches et d'isolation (Deptrac).

**Conséquences.** Le noyau partagé reste minimal ; `System` sert d'exemple de référence du flux
Provider → QueryBus → Handler → port → adapter.

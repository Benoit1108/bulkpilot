# 0009 — Mapping Doctrine XML hors du domaine

**Contexte.** Des attributs `#[ORM\…]` dans les entités rendraient le domaine dépendant de Doctrine.

**Décision.** Mapping XML dans `<Context>/Infrastructure/Persistence/Doctrine/Mapping`, déclaré par contexte
dans `config/packages/doctrine.yaml`. Types Doctrine personnalisés pour les value objects (identifiants, quantités).

**Conséquences.** Le domaine reste du PHP pur, vérifié par Deptrac ; le mapping est un peu plus verbeux et validé
par `doctrine:schema:validate` en CI.

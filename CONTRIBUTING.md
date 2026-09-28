# Contribuer

## Branches et commits

- `main` est protégée ; tout passe par une PR dont la CI est verte.
- Commits au format [Conventional Commits](https://www.conventionalcommits.org/) : `feat(nutrition): …`, `fix(training): …`.

## Avant d'ouvrir une PR

```bash
make qa
make e2e-prod
```

## Definition of Done

Une fonctionnalité est terminée quand : code et tests écrits, tests verts, PHPStan / Deptrac / linters verts,
API documentée (spec OpenAPI régénérée), documentation à jour, responsive et accessibilité vérifiés,
sécurité vérifiée, aucune régression.

## Règles

- Comprendre l'existant avant de modifier ; ne pas supprimer une fonctionnalité ni remplacer une architecture sans décision.
- Ne jamais contourner un test ni désactiver une règle pour faire passer la CI.
- Toute décision structurante fait l'objet d'un ADR dans `docs/adr/`.
- Toute dette technique volontaire est documentée (ADR ou TODO référencé).

# 0008 — Authentification par session cookie httpOnly

**Contexte.** Besoins : liste des appareils connectés, révocation d'une session, 2FA. PWA et API partagent une origine.

**Décision.** Sessions Symfony stockées en base, cookie `httpOnly`, `Secure`, `SameSite=Lax`.
Protection CSRF par `SameSite` et vérification de l'en-tête `Origin` sur les requêtes mutantes. Pas de JWT dans le navigateur.

**Conséquences.** Révocation immédiate et côté serveur, aucun jeton lisible par du JavaScript.
Une future application native utiliserait un flux dédié (nouvel ADR).

# 0017 — Double authentification TOTP

**Contexte.** 2FA demandée sans service payant ni dépendance à l'email ; utilisation principale sur téléphone.

**Décision.**
- TOTP RFC 6238 (SHA-1, 6 chiffres, 30 s — compatible avec toutes les applications), tolérance ±1 pas, anti-rejeu
  (dernier pas accepté mémorisé). Graine de 160 bits chiffrée au repos (libsodium secretbox, sous-clé dérivée de `APP_ENCRYPTION_KEY`, format versionné `v1:`).
- Activation en deux temps (graine en attente → premier code valide), mot de passe exigé pour activer, désactiver
  ou régénérer les codes, email vérifié requis.
- 10 codes de secours (≈ 50 bits) stockés en hachage à clé BLAKE2b (sous-clé dérivée de `APP_ENCRYPTION_KEY`) :
  vérification instantanée, inutilisables hors ligne sans la clé serveur. Un hachage lent (Argon2) ×10 coûtait plusieurs
  secondes par connexion — rejeté après mesure en E2E.
- Codes TOTP et de secours protégés contre l'usage simultané par un verrou optimiste sur `User`.
- Connexion : après le bon mot de passe, une « connexion en attente » (5 min) est gardée en session serveur ;
  5 essais de code par compte et par 15 min.

**Conséquences.** Perdre `APP_ENCRYPTION_KEY` invalide les authentificateurs (les codes de secours aussi) ; sa rotation
se fera via le préfixe de version. Les passkeys (WebAuthn) pourront s'ajouter comme second facteur supplémentaire.

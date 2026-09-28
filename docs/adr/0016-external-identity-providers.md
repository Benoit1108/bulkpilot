# 0016 — Fournisseurs d'identité externes (Google, Apple)

**Contexte.** Le cahier des charges demande Google et l'architecture d'Apple Sign In, sans service payant pour démarrer.

**Décision.**
- Port `ExternalIdentityProvider` (Application) : URL d'autorisation, échange du code → `ExternalProfile`.
  Adapters : `GoogleIdentityProvider` (OIDC, activé si `GOOGLE_CLIENT_ID`/`SECRET` sont définis) et
  `FakeIdentityProvider` (dev/test seulement, classe non enregistrée en production).
- Flux « authorization code » + PKCE + `state` stocké en session serveur ; l'échange du code et la lecture du profil
  (endpoint userinfo, TLS) se font côté serveur. Le navigateur ne voit ni code d'accès ni jeton.
- `ExternalIdentity` (fournisseur + sujet stable) est lié à un `User`. Liaison automatique par email seulement si
  les deux côtés ont vérifié l'adresse ; sinon refus (`identity.external_account_conflict`).
- La 2FA s'applique aussi après un fournisseur externe.
- **Apple** : le fournisseur existe dans l'énumération ; l'adapter (client secret = JWT signé avec la clé Apple,
  réponse en `form_post`, nom transmis seulement à la première connexion) sera ajouté avec un compte développeur Apple,
  sans autre changement que la classe d'adapter.

**Conséquences.** Ajouter un fournisseur = une classe d'Infrastructure. Pas de dépendance à une librairie OAuth tierce ;
le flux complet est testé contre le faux fournisseur (tests API) et l'adapter Google contre un client HTTP simulé.

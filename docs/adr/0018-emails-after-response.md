# 0018 — Emails envoyés après la réponse

**Contexte.** L'envoi SMTP synchrone rendait la demande de réinitialisation mesurablement plus lente pour un compte
existant (≈ 55 ms contre 9 ms), révélant les comptes, et faisait échouer l'inscription en cas de panne SMTP.

**Décision.** `DeferredMailer` met les emails en file pendant la requête et les envoie sur `kernel.terminate`
(après l'envoi de la réponse ; aussi `console.terminate`). Un échec d'envoi est journalisé. Le lien (secret) reste créé
dans la transaction de la commande ; l'utilisateur peut en redemander un. La file est vidée par `kernel.reset` (worker).

**Conséquences.** Temps de réponse indépendants de l'envoi ; pas de retry automatique : le passage à un transport
Messenger asynchrone (retries, file `failed`) viendra avec le premier worker (ADR-0014).

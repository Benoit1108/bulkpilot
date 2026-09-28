# 0014 — Livraison des événements de domaine

**Contexte.** Les événements publiés pendant une commande ne doivent être visibles que si elle réussit. Un abonné
qui échoue après le commit ne doit pas faire croire au client que son écriture a échoué (il réessaierait → doublon).

**Décision.**
- Les handlers publient via `EventBus::publish()` ; la livraison est différée jusqu'au succès de la commande
  (`DispatchAfterCurrentBusStamp`) et abandonnée si elle échoue.
- `event.bus` enveloppe **chaque livraison** dans sa propre transaction Doctrine : les écritures des abonnés sont persistées.
- Un échec d'abonné synchrone après commit est journalisé (`error`) et **n'échoue pas** la commande.
- Une réaction qui ne doit pas être perdue (notification, recalcul, IA) est routée vers le transport `async`
  (retries, puis file `failed`), ce qui la rend rejouable.
- Les abonnés sont idempotents.

**Conséquences.** Écritures jamais dupliquées par un retry client ; les réactions synchrones sont « au mieux ».
Comportement verrouillé par `BusWiringTest`. Un outbox transactionnel pourra remplacer le transport async si la
garantie « exactement une fois » devient nécessaire.

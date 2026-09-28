<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Application\Bus\Query\QueryBus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class MessengerQueryBus implements QueryBus
{
    use UnwrapsHandlerFailures;

    public function __construct(
        #[Autowire(service: 'query.bus')]
        private MessageBusInterface $bus,
    ) {
    }

    public function ask(Query $query): mixed
    {
        $envelope = $this->unwrap(fn () => $this->bus->dispatch($query));

        $handled = $envelope->all(HandledStamp::class);
        if (1 !== \count($handled)) {
            throw new \LogicException(\sprintf('Query "%s" must be handled by exactly one handler, %d found.', $query::class, \count($handled)));
        }

        return $handled[0]->getResult();
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\Job\Job;
use App\Shared\Application\Bus\Job\JobBus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerJobBus implements JobBus
{
    use UnwrapsHandlerFailures;

    public function __construct(
        #[Autowire(service: 'job.bus')]
        private MessageBusInterface $bus,
    ) {
    }

    public function dispatch(Job $job): void
    {
        $this->unwrap(fn () => $this->bus->dispatch($job));
    }
}

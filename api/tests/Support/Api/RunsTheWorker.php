<?php

declare(strict_types=1);

namespace App\Tests\Support\Api;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Does what the worker does, on the test's in-memory `async` transport: each queued message is
 * handled on the bus it was sent from, until nothing is left (a message may queue others: the
 * files deleted after an erasure).
 */
trait RunsTheWorker
{
    /**
     * @param class-string|null $only handle these messages only (the others stay queued)
     *
     * @return int the messages handled
     */
    private function runTheWorker(?string $only = null): int
    {
        $container = self::getContainer();
        /** @var InMemoryTransport $async */
        $async = $container->get('messenger.transport.async');
        $handled = 0;
        for ($round = 0; $round < 10; ++$round) {
            $queued = $async->getSent();
            $async->reset();
            $kept = [];
            foreach ($queued as $envelope) {
                if (null !== $only && !$envelope->getMessage() instanceof $only) {
                    $kept[] = $envelope;
                    continue;
                }
                $this->handleOnItsBus($envelope);
                ++$handled;
            }
            $sentMeanwhile = $async->getSent();
            $async->reset();
            foreach ([...$kept, ...$sentMeanwhile] as $envelope) {
                $async->send($envelope);
            }
            if ([] === $sentMeanwhile || null !== $only && [] === array_filter($sentMeanwhile, static fn (Envelope $e): bool => $e->getMessage() instanceof $only)) {
                break;
            }
        }

        return $handled;
    }

    private function handleOnItsBus(Envelope $envelope): void
    {
        $bus = $envelope->last(BusNameStamp::class)?->getBusName() ?? 'command.bus';
        /** @var MessageBusInterface $messageBus */
        $messageBus = self::getContainer()->get($bus);
        $messageBus->dispatch($envelope->withoutAll(ReceivedStamp::class)->with(new ReceivedStamp('async')));
    }
}

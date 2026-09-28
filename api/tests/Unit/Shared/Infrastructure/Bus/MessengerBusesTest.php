<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Infrastructure\Bus\MessengerCommandBus;
use App\Shared\Infrastructure\Bus\MessengerEventBus;
use App\Shared\Infrastructure\Bus\MessengerQueryBus;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

final class MessengerBusesTest extends TestCase
{
    public function testQueryBusReturnsTheHandlerResult(): void
    {
        $bus = new MessengerQueryBus(self::bus([AskAnswer::class => [static fn (AskAnswer $q): int => 42]]));

        self::assertSame(42, $bus->ask(new AskAnswer()));
    }

    public function testQueryBusRefusesSeveralHandlers(): void
    {
        $bus = new MessengerQueryBus(self::bus([AskAnswer::class => [
            static fn (): int => 1,
            static fn (): int => 2,
        ]]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('exactly one handler, 2 found');

        $bus->ask(new AskAnswer());
    }

    public function testCommandBusRethrowsTheOriginalException(): void
    {
        $bus = new MessengerCommandBus(self::bus([DoSomething::class => [
            static function (): never {
                throw new \DomainException('business rule violated');
            },
        ]]), new NullLogger());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('business rule violated');

        $bus->dispatch(new DoSomething());
    }

    public function testCommandBusRunsTheHandler(): void
    {
        $handled = [];
        $bus = new MessengerCommandBus(self::bus([DoSomething::class => [
            static function (DoSomething $command) use (&$handled): void {
                $handled[] = $command;
            },
        ]]), new NullLogger());

        $bus->dispatch($command = new DoSomething());

        self::assertSame([$command], $handled);
    }

    public function testNestedWrappersAreUnwrappedToTheSingleOriginalException(): void
    {
        $original = new \DomainException('deep');
        $failing = new class($original) implements MessageBusInterface {
            public function __construct(private \Throwable $original)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $envelope = new Envelope($message);

                throw new HandlerFailedException($envelope, [new HandlerFailedException($envelope, [$this->original])]);
            }
        };

        try {
            new MessengerCommandBus($failing, new NullLogger())->dispatch(new DoSomething());
            self::fail('Expected exception');
        } catch (\DomainException $e) {
            self::assertSame($original, $e);
        }
    }

    public function testASubscriberFailureAfterCommitIsLoggedNotRethrown(): void
    {
        $failing = new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $envelope = new Envelope($message);

                throw new DelayedMessageHandlingException([new HandlerFailedException($envelope, [new \RuntimeException('subscriber')])], $envelope);
            }
        };
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = \sprintf('%s: %s', \is_string($level) ? $level : 'unknown', (string) $message);
            }
        };

        new MessengerCommandBus($failing, $logger)->dispatch(new DoSomething());

        self::assertSame(['error: A domain event subscriber failed after command {command} was committed.'], $logger->records);
    }

    public function testQueryBusRefusesAQueryWithoutHandler(): void
    {
        $this->expectException(NoHandlerForMessageException::class);

        new MessengerQueryBus(self::bus([]))->ask(new AskAnswer());
    }

    public function testSeveralFailuresAreKeptWrapped(): void
    {
        $bus = new MessengerEventBus(self::bus([SomethingHappened::class => [
            static function (): never {
                throw new \RuntimeException('first');
            },
            static function (): never {
                throw new \RuntimeException('second');
            },
        ]], allowNoHandlers: true));

        $this->expectException(HandlerFailedException::class);

        $bus->publish(new SomethingHappened());
    }

    public function testEventsWithoutSubscribersAreAccepted(): void
    {
        $this->expectNotToPerformAssertions();

        new MessengerEventBus(self::bus([], allowNoHandlers: true))->publish(new SomethingHappened());
    }

    public function testEventsAreDeliveredToEverySubscriber(): void
    {
        $received = [];
        $bus = new MessengerEventBus(self::bus([SomethingHappened::class => [
            static function () use (&$received): void {
                $received[] = 'a';
            },
            static function () use (&$received): void {
                $received[] = 'b';
            },
        ]], allowNoHandlers: true));

        $bus->publish(new SomethingHappened(), new SomethingHappened());

        self::assertSame(['a', 'b', 'a', 'b'], $received);
    }

    /**
     * Closures all share the same handler name: an alias keeps each one distinct,
     * as real handler services are.
     *
     * @param array<class-string, list<\Closure>> $handlers
     */
    private static function bus(array $handlers, bool $allowNoHandlers = false): MessageBus
    {
        $descriptors = array_map(
            static fn (array $closures): array => array_map(
                static fn (\Closure $closure, int $i): HandlerDescriptor => new HandlerDescriptor($closure, ['alias' => 'handler'.$i]),
                $closures,
                array_keys($closures),
            ),
            $handlers,
        );

        return new MessageBus([
            new DispatchAfterCurrentBusMiddleware(),
            new HandleMessageMiddleware(new HandlersLocator($descriptors), $allowNoHandlers),
        ]);
    }
}

/**
 * @implements Query<int>
 */
final readonly class AskAnswer implements Query
{
}

final readonly class DoSomething implements Command
{
}

final readonly class SomethingHappened implements DomainEvent
{
    public function occurredOn(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-28');
    }
}

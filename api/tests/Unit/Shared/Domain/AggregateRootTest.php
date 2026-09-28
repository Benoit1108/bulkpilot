<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Event\DomainEvent;
use PHPUnit\Framework\TestCase;

final class AggregateRootTest extends TestCase
{
    public function testRecordedEventsArePulledOnceInOrder(): void
    {
        $aggregate = new SomeAggregate();
        $first = new SomethingHappened(new \DateTimeImmutable('2026-09-28 08:00:00'));
        $second = new SomethingHappened(new \DateTimeImmutable('2026-09-28 08:00:01'));

        $aggregate->doSomething($first);
        $aggregate->doSomething($second);

        self::assertSame([$first, $second], $aggregate->pullDomainEvents());
        self::assertSame([], $aggregate->pullDomainEvents());
    }
}

final class SomeAggregate extends AggregateRoot
{
    public function doSomething(DomainEvent $event): void
    {
        $this->recordThat($event);
    }
}

final readonly class SomethingHappened implements DomainEvent
{
    public function __construct(private \DateTimeImmutable $occurredOn)
    {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}

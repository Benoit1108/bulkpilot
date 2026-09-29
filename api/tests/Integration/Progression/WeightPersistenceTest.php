<?php

declare(strict_types=1);

namespace App\Tests\Integration\Progression;

use App\Progression\Domain\Goal\WeightGoal;
use App\Progression\Domain\Goal\WeightGoalId;
use App\Progression\Domain\Goal\WeightGoalRepository;
use App\Progression\Domain\Weight\DayWeight;
use App\Progression\Domain\Weight\WeighIn;
use App\Progression\Domain\Weight\WeighInId;
use App\Progression\Domain\Weight\WeighInRepository;
use App\Progression\Domain\Weight\Weight;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WeightPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private WeighInRepository $weighIns;
    private WeightGoalRepository $goals;
    private UserId $alex;
    private UserId $sam;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->weighIns = self::getContainer()->get(WeighInRepository::class);
        $this->goals = self::getContainer()->get(WeightGoalRepository::class);
        $this->alex = new UserId(self::id());
        $this->sam = new UserId(self::id());
        $this->now = new \DateTimeImmutable('2026-09-29 08:00:00');
    }

    public function testWeighInsAreReadByDayForTheirOwnerOnly(): void
    {
        foreach (['2026-09-20' => 60.0, '2026-09-25' => 60.6, '2026-09-29' => 61.2] as $day => $kg) {
            $this->weighIns->save(WeighIn::record(new WeighInId(self::id()), $this->alex, new \DateTimeImmutable($day), new Weight($kg), $this->now));
        }
        $this->weighIns->save(WeighIn::record(new WeighInId(self::id()), $this->sam, new \DateTimeImmutable('2026-09-10'), new Weight(90.0), $this->now));
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(60.6, $this->weighIns->on($this->alex, new \DateTimeImmutable('2026-09-25'))?->weight()->kg);
        self::assertSame('2026-09-20', $this->weighIns->first($this->alex)?->day()->format('Y-m-d'));
        self::assertSame(['2026-09-25', '2026-09-29'], self::days($this->weighIns->since($this->alex, new \DateTimeImmutable('2026-09-25'))));
        self::assertCount(3, $this->weighIns->since($this->alex, null));
        self::assertSame(['2026-09-25', '2026-09-20'], self::days($this->weighIns->latest($this->alex, 5, new \DateTimeImmutable('2026-09-29'))));
        self::assertNull($this->weighIns->on($this->sam, new \DateTimeImmutable('2026-09-25')));
    }

    public function testTheDatabaseKeepsOneWeighInADay(): void
    {
        $day = new \DateTimeImmutable('2026-09-28');
        $this->weighIns->save(WeighIn::record(new WeighInId(self::id()), $this->alex, $day, new Weight(60.0), $this->now));
        $this->entityManager->flush();
        $this->weighIns->save(WeighIn::record(new WeighInId(self::id()), $this->alex, $day, new Weight(61.0), $this->now));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testOneGoalIsFollowedAtATimeAndAnEndedOneIsKept(): void
    {
        $start = new DayWeight(new \DateTimeImmutable('2026-09-01'), 48.0);
        $first = WeightGoal::set(new WeightGoalId(self::id()), $this->alex, $start, new Weight(60.0), [new Weight(52.0), new Weight(50.0)], $this->now);
        $this->goals->save($first);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $read = $this->goals->current($this->alex);
        self::assertSame([50.0, 52.0, 60.0], $read?->steps());
        $read->end($this->now);
        $this->goals->save($read);
        $this->goals->save(WeightGoal::set(new WeightGoalId(self::id()), $this->alex, $start, new Weight(55.0), [], $this->now));
        $this->entityManager->flush();
        $this->entityManager->clear();
        self::assertSame(55.0, $this->goals->current($this->alex)?->targetKg());

        $this->goals->save(WeightGoal::set(new WeightGoalId(self::id()), $this->alex, $start, new Weight(58.0), [], $this->now));
        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    /**
     * @param list<WeighIn> $weighIns
     *
     * @return list<string>
     */
    private static function days(array $weighIns): array
    {
        return array_map(static fn (WeighIn $weighIn): string => $weighIn->day()->format('Y-m-d'), $weighIns);
    }

    private static function id(): string
    {
        return \Symfony\Component\Uid\Uuid::v7()->toRfc4122();
    }
}

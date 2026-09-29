<?php

declare(strict_types=1);

namespace App\Tests\Integration\Progression;

use App\Progression\Domain\Measurement\CustomMeasure;
use App\Progression\Domain\Measurement\CustomMeasureId;
use App\Progression\Domain\Measurement\CustomMeasureRepository;
use App\Progression\Domain\Measurement\Length;
use App\Progression\Domain\Measurement\MeasurementDay;
use App\Progression\Domain\Measurement\MeasurementDayId;
use App\Progression\Domain\Measurement\MeasurementDayRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class MeasurementPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private MeasurementDayRepository $days;
    private CustomMeasureRepository $measures;
    private UserId $alex;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->days = self::getContainer()->get(MeasurementDayRepository::class);
        $this->measures = self::getContainer()->get(CustomMeasureRepository::class);
        $this->alex = new UserId(Uuid::v7()->toRfc4122());
        $this->now = new \DateTimeImmutable('2026-09-29 08:00:00');
    }

    public function testADayKeepsItsValuesByMeasure(): void
    {
        $hips = CustomMeasure::add(new CustomMeasureId(Uuid::v7()->toRfc4122()), $this->alex, 'Hanches', $this->now);
        $this->measures->save($hips);
        foreach (['2026-09-01' => 80.0, '2026-09-20' => 79.0] as $day => $waist) {
            $this->days->save(MeasurementDay::record(new MeasurementDayId(Uuid::v7()->toRfc4122()), $this->alex, new \DateTimeImmutable($day), ['waist' => new Length($waist), $hips->key() => new Length(95.0)], $this->now));
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(['waist' => 79.0, $hips->key() => 95.0], $this->days->on($this->alex, new \DateTimeImmutable('2026-09-20'))?->values());
        self::assertSame(['2026-09-01', '2026-09-20'], array_map(static fn (MeasurementDay $day): string => $day->day()->format('Y-m-d'), $this->days->ofUser($this->alex)));

        $read = $this->measures->getOwnedBy($hips->id(), $this->alex);
        $read->archive($this->now);
        $this->entityManager->flush();
        self::assertSame([], $this->measures->ofUser($this->alex));
        self::assertCount(1, $this->measures->ofUser($this->alex, withArchived: true));
    }

    public function testTheDatabaseKeepsOneSetOfMeasurementsADay(): void
    {
        foreach ([80.0, 81.0] as $waist) {
            $this->days->save(MeasurementDay::record(new MeasurementDayId(Uuid::v7()->toRfc4122()), $this->alex, new \DateTimeImmutable('2026-09-28'), ['waist' => new Length($waist)], $this->now));
        }

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }
}

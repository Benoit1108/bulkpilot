<?php

declare(strict_types=1);

namespace App\Tests\Unit\Training\Domain;

use App\Training\Domain\Exception\InvalidPrescription;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Prescription\Rest;
use App\Training\Domain\Prescription\Rir;
use App\Training\Domain\Prescription\Tempo;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class PrescriptionTest extends TestCase
{
    #[TestWith(['3-1-1-0', '3-1-1-0'])]
    #[TestWith(['3110', '3-1-1-0'])]
    #[TestWith(['2-0-x-0', '2-0-X-0'])]
    #[TestWith([' 4 / 0 / 1 / 2 ', '4-0-1-2'])]
    #[TestWith(['10-0-1-0', '10-0-1-0'])]
    public function testATempoIsReadInItsUsualForms(string $tempo, string $normalised): void
    {
        self::assertSame($normalised, new Tempo($tempo)->value);
    }

    #[TestWith(['3-1-1'])]
    #[TestWith(['3-1-1-0-0'])]
    #[TestWith(['11-0-1-0'])]
    #[TestWith(['lent'])]
    #[TestWith([''])]
    public function testOtherTemposAreRefused(string $tempo): void
    {
        $this->expectException(InvalidPrescription::class);

        new Tempo($tempo);
    }

    public function testARepRangeGoesUp(): void
    {
        self::assertTrue(new RepRange(8, 12)->equals(new RepRange(8, 12)));
        self::assertFalse(new RepRange(8, 12)->equals(new RepRange(8, 10)));
        self::assertSame(5, new RepRange(5, 5)->max, 'a single target');

        foreach ([[0, 5], [12, 8], [1, RepRange::MAX + 1]] as [$min, $max]) {
            try {
                new RepRange($min, $max);
                self::fail(\sprintf('%d-%d is refused', $min, $max));
            } catch (InvalidPrescription $e) {
                self::assertSame('training.invalid_prescription', $e->errorCode());
            }
        }
    }

    public function testEffortAndRestAreBounded(): void
    {
        self::assertNull(Rir::valid(null));
        self::assertSame(0, Rir::valid(0));
        self::assertSame(0, Rest::valid(0));
        self::assertSame(Rest::MAX_SECONDS, Rest::valid(Rest::MAX_SECONDS));

        $this->expectException(InvalidPrescription::class);
        Rir::valid(Rir::MAX + 1);
    }

    public function testARestCannotBeNegative(): void
    {
        $this->expectException(InvalidPrescription::class);

        Rest::valid(-1);
    }
}

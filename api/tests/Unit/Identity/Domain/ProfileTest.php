<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Exception\InvalidProfile;
use App\Identity\Domain\User\Profile;
use App\Identity\Domain\User\Sex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProfileTest extends TestCase
{
    private \DateTimeImmutable $today;

    protected function setUp(): void
    {
        $this->today = new \DateTimeImmutable('2026-09-28');
    }

    public function testAFullProfile(): void
    {
        $profile = new Profile(' Alex ', $this->today, new \DateTimeImmutable('2000-01-15'), Sex::Male, 178);

        self::assertSame('Alex', $profile->firstName);
        self::assertSame(26, $profile->ageOn($this->today));
        self::assertSame(Sex::Male, $profile->sex);
        self::assertSame(178, $profile->heightCm);
    }

    public function testOnlyTheFirstNameIsRequired(): void
    {
        $profile = new Profile('Alex', $this->today);

        self::assertNull($profile->ageOn($this->today));
    }

    #[DataProvider('invalid')]
    public function testItRejectsImplausibleValues(string $firstName, ?string $birthDate, ?int $height): void
    {
        $this->expectException(InvalidProfile::class);

        new Profile($firstName, $this->today, null === $birthDate ? null : new \DateTimeImmutable($birthDate), null, $height);
    }

    /**
     * @return iterable<string, array{string, ?string, ?int}>
     */
    public static function invalid(): iterable
    {
        yield 'blank first name' => ['  ', null, null];
        yield 'first name too long' => [str_repeat('a', 51), null, null];
        yield 'born in the future' => ['Alex', '2027-01-01', null];
        yield 'too young' => ['Alex', '2015-01-01', null];
        yield 'too old' => ['Alex', '1900-01-01', null];
        yield 'too short' => ['Alex', null, 99];
        yield 'too tall' => ['Alex', null, 251];
    }

    public function testBoundariesAreAccepted(): void
    {
        $this->expectNotToPerformAssertions();

        new Profile('Alex', $this->today, new \DateTimeImmutable('2013-09-28'), null, 100);
        new Profile('Alex', $this->today, null, null, 250);
    }
}

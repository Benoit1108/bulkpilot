<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Dashboard\DashboardLayout;
use App\Identity\Domain\Dashboard\Widget;
use App\Identity\Domain\Exception\InvalidEmailAddress;
use App\Identity\Domain\User\Profile;
use PHPUnit\Framework\TestCase;

/**
 * The edges of Identity's rules.
 */
final class BoundariesTest extends TestCase
{
    public function testAFirstNameCountsCharactersAndTheOldestAgeIsAllowed(): void
    {
        $today = new \DateTimeImmutable('2026-09-30');

        self::assertSame(50, mb_strlen(new Profile(str_repeat('é', 50), $today)->firstName));
        self::assertSame(Profile::MAX_AGE, new Profile('Alex', $today, $today->modify(\sprintf('-%d years', Profile::MAX_AGE)))->ageOn($today));
    }

    public function testAnInvalidAddressIsQuotedOnItsFirstEightyCharacters(): void
    {
        $value = str_repeat('é', 90);

        self::assertSame('"'.str_repeat('é', 80).'" is not a valid email address.', InvalidEmailAddress::fromValue($value)->getMessage());
    }

    public function testALayoutIsAListOfEveryWidgetNewOnesShown(): void
    {
        $arranged = DashboardLayout::arranged(array_map(static fn (Widget $widget): array => ['key' => $widget->value, 'visible' => false], array_reverse(Widget::cases())))->toArray();
        self::assertSame(range(0, \count(Widget::cases()) - 1), array_keys($arranged));
        self::assertSame(array_reverse(array_map(static fn (Widget $widget): string => $widget->value, Widget::cases())), array_column($arranged, 'key'));

        $stored = DashboardLayout::stored([['key' => 'coach', 'visible' => false]])->toArray();
        self::assertSame(range(0, \count(Widget::cases()) - 1), array_keys($stored));
        self::assertSame(['coach', false], [$stored[0]['key'], $stored[0]['visible']]);
        self::assertTrue($stored[1]['visible'], 'a widget the saved layout did not know is shown');
    }
}

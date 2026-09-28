<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Exception\InvalidEmailAddress;
use App\Identity\Domain\User\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailAddressTest extends TestCase
{
    public function testItIsTrimmedAndLowercased(): void
    {
        self::assertSame('alex@example.com', new EmailAddress('  Alex@Example.COM ')->value);
        self::assertTrue(new EmailAddress('ALEX@example.com')->equals(new EmailAddress('alex@example.com')));
    }

    #[DataProvider('invalid')]
    public function testItRejectsInvalidAddresses(string $value): void
    {
        $this->expectException(InvalidEmailAddress::class);

        new EmailAddress($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalid(): iterable
    {
        yield 'empty' => [''];
        yield 'no at' => ['alex.example.com'];
        yield 'no domain' => ['alex@'];
        yield 'too long' => [str_repeat('a', 250).'@example.com'];
    }
}

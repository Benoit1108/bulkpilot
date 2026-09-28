<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain;

use App\Shared\Domain\Exception\InvalidIdentifier;
use App\Shared\Domain\Identifier\Uuid;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    public function testItKeepsACanonicalValue(): void
    {
        $id = new SomeId('0199a0b1-7c3e-7d4a-9b2f-1c2d3e4f5a6b');

        self::assertSame('0199a0b1-7c3e-7d4a-9b2f-1c2d3e4f5a6b', $id->value);
        self::assertSame('0199a0b1-7c3e-7d4a-9b2f-1c2d3e4f5a6b', (string) $id);
    }

    public function testItNormalizesCase(): void
    {
        self::assertSame(
            '0199a0b1-7c3e-7d4a-9b2f-1c2d3e4f5a6b',
            new SomeId('0199A0B1-7C3E-7D4A-9B2F-1C2D3E4F5A6B')->value,
        );
    }

    #[DataProvider('invalidValues')]
    public function testItRejectsInvalidValues(string $value): void
    {
        $this->expectException(InvalidIdentifier::class);

        new SomeId($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'empty' => [''];
        yield 'garbage' => ['not-a-uuid'];
        yield 'braces' => ['{0199a0b1-7c3e-7d4a-9b2f-1c2d3e4f5a6b}'];
        yield 'missing dashes' => ['0199a0b17c3e7d4a9b2f1c2d3e4f5a6b'];
        yield 'trailing newline' => ["0199a0b1-7c3e-7d4a-9b2f-1c2d3e4f5a6b\n"];
    }

    public function testEqualityRequiresSameTypeAndValue(): void
    {
        $value = '0199a0b1-7c3e-7d4a-9b2f-1c2d3e4f5a6b';

        self::assertTrue(new SomeId($value)->equals(new SomeId($value)));
        self::assertFalse(new SomeId($value)->equals(new SomeId('0199a0b1-7c3e-7d4a-9b2f-1c2d3e4f5a6c')));
        self::assertFalse(new SomeId($value)->equals(new OtherId($value)));
    }

    public function testInvalidIdentifierExposesAStableErrorCode(): void
    {
        try {
            new SomeId('nope');
            self::fail('Expected exception');
        } catch (InvalidIdentifier $e) {
            self::assertSame('shared.invalid_identifier', $e->errorCode());
        }
    }
}

final readonly class SomeId extends Uuid
{
}

final readonly class OtherId extends Uuid
{
}

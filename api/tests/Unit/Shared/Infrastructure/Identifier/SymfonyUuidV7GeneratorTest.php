<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Identifier;

use App\Shared\Domain\Identifier\Uuid;
use App\Shared\Infrastructure\Identifier\SymfonyUuidV7Generator;
use PHPUnit\Framework\TestCase;

final class SymfonyUuidV7GeneratorTest extends TestCase
{
    public function testItGeneratesCanonicalTimeOrderedV7Identifiers(): void
    {
        $generator = new SymfonyUuidV7Generator();

        $ids = [];
        for ($i = 0; $i < 50; ++$i) {
            $ids[] = $generator->generate();
        }

        foreach ($ids as $id) {
            self::assertSame($id, new GeneratedId($id)->value, 'canonical lowercase form');
            self::assertSame('7', $id[14], 'version nibble');
        }
        $sorted = $ids;
        sort($sorted);
        self::assertSame($ids, $sorted, 'monotonic within the same process');
        self::assertCount(50, array_unique($ids));
    }
}

final readonly class GeneratedId extends Uuid
{
}

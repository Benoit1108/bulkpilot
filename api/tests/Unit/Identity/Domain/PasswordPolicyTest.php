<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Exception\WeakPassword;
use App\Identity\Domain\User\PasswordPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public function testLongPassphrasesAreAccepted(): void
    {
        $this->expectNotToPerformAssertions();

        PasswordPolicy::assertAcceptable('twelve chars');
        PasswordPolicy::assertAcceptable(str_repeat('é', 4096));
    }

    #[DataProvider('rejected')]
    public function testLengthIsEnforced(string $password): void
    {
        $this->expectException(WeakPassword::class);

        PasswordPolicy::assertAcceptable($password);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejected(): iterable
    {
        yield 'eleven characters' => ['elevenchars'];
        yield 'too long' => [str_repeat('a', 4097)];
    }
}

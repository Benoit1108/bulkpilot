<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Crypto;

use App\Identity\Application\Port\RecoveryCodeGenerator;

/**
 * "k7m3p-x9w2c": 10 characters from a 31-letter alphabet without look-alikes (≈ 50 bits).
 */
final readonly class RandomRecoveryCodeGenerator implements RecoveryCodeGenerator
{
    private const string ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public function generate(int $count): array
    {
        $codes = [];
        for ($i = 0; $i < $count; ++$i) {
            $code = '';
            for ($c = 0; $c < 10; ++$c) {
                $code .= self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)];
            }
            $codes[] = substr($code, 0, 5).'-'.substr($code, 5);
        }

        return $codes;
    }
}

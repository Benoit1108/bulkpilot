<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Pseudonym;

use App\Audit\Application\Port\Pseudonyms;
use App\Shared\Domain\Identifier\UserId;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * HMAC-SHA256 of the user id with a key derived from the application secret: stable (the same
 * account's events stay together) but not reversible without the secret.
 */
final readonly class HmacPseudonyms implements Pseudonyms
{
    private string $key;

    public function __construct(#[Autowire('%kernel.secret%')] string $secret)
    {
        $this->key = hash_hkdf('sha256', $secret, info: 'audit-pseudonyms');
    }

    public function of(UserId $userId): string
    {
        return hash_hmac('sha256', $userId->value, $this->key);
    }
}

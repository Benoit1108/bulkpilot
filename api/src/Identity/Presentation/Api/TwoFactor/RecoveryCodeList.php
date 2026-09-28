<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\TwoFactor;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Identity\Domain\User\User;

/**
 * Plain recovery codes, returned exactly once: only their hashes are stored.
 */
#[ApiResource(
    shortName: 'RecoveryCodes',
    operations: [
        new Post(
            uriTemplate: '/me/two-factor/confirm',
            status: 200,
            description: 'Activates two-factor authentication with a first valid code; returns the recovery codes.',
            input: CodeInput::class,
            processor: TwoFactorConfirmProcessor::class,
        ),
        new Post(
            uriTemplate: '/me/two-factor/recovery-codes',
            status: 200,
            description: 'Replaces the recovery codes (password required); previous ones stop working.',
            input: PasswordConfirmation::class,
            processor: RecoveryCodesProcessor::class,
        ),
    ],
)]
final readonly class RecoveryCodeList
{
    /**
     * @param list<string> $recoveryCodes
     */
    public function __construct(
        #[ApiProperty(required: true, schema: ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => User::RECOVERY_CODE_COUNT, 'maxItems' => User::RECOVERY_CODE_COUNT])]
        public array $recoveryCodes,
    ) {
    }
}

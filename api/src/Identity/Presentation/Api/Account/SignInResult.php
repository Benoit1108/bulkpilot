<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Account;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;

/**
 * Either the device is signed in (`account`), or a second factor is expected
 * (`twoFactorRequired`, then POST /auth/sign-in/second-factor).
 */
#[ApiResource(
    shortName: 'SignIn',
    operations: [
        new Post(
            uriTemplate: '/auth/sign-in',
            status: 200,
            description: 'Checks email and password; signs the device in, or asks for the second factor.',
            input: SignInInput::class,
            processor: SignInProcessor::class,
        ),
        new Post(
            uriTemplate: '/auth/sign-in/second-factor',
            status: 200,
            description: 'Completes a pending sign-in with an authenticator code or a recovery code.',
            input: SecondFactorInput::class,
            processor: SecondFactorProcessor::class,
        ),
    ],
)]
final readonly class SignInResult
{
    public function __construct(
        #[ApiProperty(required: true)]
        public bool $twoFactorRequired,
        #[ApiProperty(required: true, readableLink: true, genId: false)]
        public ?Account $account,
    ) {
    }

    public static function signedIn(Account $account): self
    {
        return new self(false, $account);
    }

    public static function secondFactorRequired(): self
    {
        return new self(true, null);
    }
}

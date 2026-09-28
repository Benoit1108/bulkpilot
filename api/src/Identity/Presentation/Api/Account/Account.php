<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Account;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Identity\Application\Account\AccountView;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

#[ApiResource(
    shortName: 'Account',
    operations: [
        new Get(uriTemplate: '/me', description: 'The signed-in account.', provider: AccountProvider::class),
        new Put(
            uriTemplate: '/me/profile',
            description: 'Replaces the profile (identity only: body metrics live in Progression).',
            input: ProfileInput::class,
            read: false,
            processor: UpdateProfileProcessor::class,
        ),
        new Post(
            uriTemplate: '/auth/register',
            description: 'Creates an account, emails a verification link and signs the device in.',
            input: RegistrationInput::class,
            processor: RegisterProcessor::class,
        ),
    ],
)]
final readonly class Account
{
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $id,
        #[ApiProperty(required: true)]
        public string $email,
        #[ApiProperty(required: true)]
        public bool $emailVerified,
        #[ApiProperty(required: true)]
        public string $firstName,
        #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
        #[ApiProperty(required: true, schema: ['type' => ['string', 'null'], 'format' => 'date'])]
        public ?\DateTimeImmutable $birthDate,
        #[ApiProperty(required: true, schema: ['type' => ['string', 'null'], 'enum' => ['female', 'male', null]])]
        public ?string $sex,
        #[ApiProperty(required: true)]
        public ?int $heightCm,
        #[ApiProperty(required: true)]
        public bool $twoFactorEnabled,
        /** False for accounts created with an external provider: a password can be set via the reset flow. */
        #[ApiProperty(required: true)]
        public bool $hasPassword,
    ) {
    }

    public static function fromView(AccountView $view): self
    {
        return new self($view->id, $view->email, $view->emailVerified, $view->firstName, $view->birthDate, $view->sex?->value, $view->heightCm, $view->twoFactorEnabled, $view->hasPassword);
    }
}

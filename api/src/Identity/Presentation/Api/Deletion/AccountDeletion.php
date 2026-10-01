<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Deletion;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Identity\Application\Deletion\ScheduleAccountDeletionHandler;
use App\Identity\Domain\User\User;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * « Supprimer mon compte » (spec §103, ADR-0030): when the account will be erased.
 */
#[ApiResource(
    shortName: 'AccountDeletion',
    operations: [
        new Post(
            uriTemplate: '/me/deletion',
            status: 202,
            description: 'Closes the account now (every session revoked, this device signed out) and erases it for good after '.User::DELETION_DELAY_DAYS.' days, data and files included; signing in again before cancels. Needs the password (`identity.wrong_current_password`); an account without one sends an empty password and must have signed in on this device within '.ScheduleAccountDeletionHandler::RECENT_SIGN_IN_MINUTES.' minutes (`identity.reauthentication_required`); plus the second factor when it is on (`identity.invalid_second_factor_code`). All 422.',
            input: DeletionInput::class,
            openapi: new Operation(responses: ['409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]),
            processor: AccountDeletionProcessor::class,
        ),
    ],
)]
final readonly class AccountDeletion
{
    public function __construct(
        #[ApiProperty(description: 'When the account is erased, unless the user signs in again before.', required: true)]
        public \DateTimeImmutable $deletionDueAt,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Deletion;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;

/**
 * The delays that apply to the user's data (spec §103–105), from the server's configuration, so
 * that the PWA never states one of its own.
 */
#[ApiResource(
    shortName: 'DataPolicy',
    operations: [
        new Get(uriTemplate: '/me/data-policy', description: 'How long things take and are kept: the account deletion, an export, the activity.', provider: DataPolicyProvider::class),
    ],
)]
final readonly class DataPolicy
{
    public function __construct(
        #[ApiProperty(identifier: true, description: 'Days between asking for the deletion and the erasure (signing in meanwhile cancels it).', required: true)]
        public int $deletionDelayDays,
        #[ApiProperty(description: 'Hours a ready export can be downloaded.', required: true)]
        public int $exportAvailableHours,
        #[ApiProperty(description: 'Days the account\'s activity is kept.', required: true)]
        public int $activityRetentionDays,
        #[ApiProperty(description: 'Minutes a sign-in stands for the password of an account without one (to confirm its deletion).', required: true)]
        public int $reauthenticationMinutes,
    ) {
    }
}

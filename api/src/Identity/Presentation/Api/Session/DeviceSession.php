<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Session;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use App\Identity\Application\Session\SessionView;

#[ApiResource(
    shortName: 'Session',
    operations: [
        new GetCollection(
            uriTemplate: '/me/sessions',
            description: 'Signed-in devices, most recently active first.',
            paginationEnabled: false,
            provider: DeviceSessionProvider::class,
        ),
        new Delete(
            uriTemplate: '/me/sessions/{id}',
            description: 'Signs a device out; it is refused on its next request.',
            read: false,
            processor: RevokeDeviceSessionProcessor::class,
        ),
    ],
)]
final readonly class DeviceSession
{
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $id,
        #[ApiProperty(required: true)]
        public string $deviceLabel,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $openedAt,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $lastSeenAt,
        #[ApiProperty(required: true)]
        public bool $current,
    ) {
    }

    public static function fromView(SessionView $view): self
    {
        return new self($view->id, $view->deviceLabel, $view->openedAt, $view->lastSeenAt, $view->current);
    }
}

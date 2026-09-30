<?php

declare(strict_types=1);

namespace App\Notification\Presentation\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * The inbox as a whole: the checks the app runs when it opens, the unread count of the bell, and
 * marking everything read.
 */
#[ApiResource(
    shortName: 'NotificationInbox',
    operations: [
        new Get(
            uriTemplate: '/notifications/unread',
            description: 'How many notifications are unread (the bell\'s badge).',
            provider: InboxProvider::class,
        ),
        new Post(
            uriTemplate: '/notifications/check',
            status: 204,
            description: 'Runs the checks for the user now (as the app opens); a few minutes after the last ones, or while the schedule checks the user, nothing happens (always 204).',
            input: false,
            output: false,
            read: false,
            openapi: new Operation(responses: ['429' => new Response(Problems::TOO_MANY)]),
            processor: InboxProcessor::class,
            name: 'notifications_check',
        ),
        new Post(
            uriTemplate: '/notifications/read-all',
            status: 204,
            description: 'Marks every notification read.',
            input: false,
            output: false,
            read: false,
            openapi: new Operation(responses: ['429' => new Response(Problems::TOO_MANY)]),
            processor: InboxProcessor::class,
            name: 'notifications_read_all',
        ),
    ],
)]
final readonly class Inbox
{
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public int $count,
    ) {
    }
}

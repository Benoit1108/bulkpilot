<?php

declare(strict_types=1);

namespace App\Notification\Presentation\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Notification\Application\Inbox\ListNotificationsHandler;
use App\Notification\Application\Inbox\NotificationView;
use App\Notification\Domain\Notification\Category;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * Something worth telling the user (spec §75), in the app until read.
 */
#[ApiResource(
    shortName: 'Notification',
    operations: [
        new GetCollection(
            uriTemplate: '/notifications',
            description: 'The notifications, the latest first.',
            paginationEnabled: false,
            provider: NotificationProvider::class,
            parameters: [
                'limit' => new QueryParameter(description: 'At most '.ListNotificationsHandler::MAX.'.', schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => ListNotificationsHandler::MAX, 'default' => 20]),
                'before' => new QueryParameter(description: 'Only those made before this time (the createdAt of the last one received, for the next page)…', schema: ['type' => 'string', 'format' => 'date-time']),
                'beforeId' => new QueryParameter(description: '…and its id (those of one check share their time).', schema: ['type' => 'string', 'format' => 'uuid']),
            ],
            openapi: new Operation(responses: ['400' => new Response(Problems::INVALID_QUERY)]),
        ),
        new Get(
            uriTemplate: '/notifications/{id}',
            requirements: ['id' => '[0-9a-f-]{36}'],
            provider: NotificationProvider::class,
            openapi: new Operation(responses: ['404' => new Response(Problems::NOT_FOUND)]),
        ),
        new Post(
            uriTemplate: '/notifications/{id}/read',
            requirements: ['id' => '[0-9a-f-]{36}'],
            status: 200,
            description: 'Marks it read (reading it again keeps the first time).',
            input: false,
            read: false,
            openapi: new Operation(responses: ['404' => new Response(Problems::NOT_FOUND), '429' => new Response(Problems::TOO_MANY)]),
            processor: NotificationProcessor::class,
            name: 'notification_read',
        ),
    ],
)]
final readonly class Notification
{
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $id,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => Category::VALUES])]
        public string $category,
        #[ApiProperty(required: true)]
        public string $title,
        #[ApiProperty(required: true)]
        public string $body,
        #[ApiProperty(description: 'The page of the PWA it leads to.', required: true)]
        public ?string $link,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $createdAt,
        #[ApiProperty(required: true)]
        public ?\DateTimeImmutable $readAt,
    ) {
    }

    public static function of(NotificationView $view): self
    {
        return new self($view->id, $view->category, $view->title, $view->body, $view->link, $view->createdAt, $view->readAt);
    }
}

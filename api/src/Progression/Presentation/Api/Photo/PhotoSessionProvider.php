<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Photo;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Progression\Application\Photo\ListPhotoSessions;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProviderInterface<PhotoSession>
 */
final readonly class PhotoSessionProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    /**
     * @return list<PhotoSession>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return array_map(PhotoSession::of(...), $this->queryBus->ask(new ListPhotoSessions($this->currentUser->id())));
    }
}

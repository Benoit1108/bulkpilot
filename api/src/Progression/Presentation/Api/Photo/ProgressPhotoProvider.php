<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Photo;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Progression\Application\Photo\GetProgressPhoto;
use App\Progression\Domain\Exception\ProgressPhotoNotFound;
use App\Progression\Domain\Photo\ProgressPhotoId;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\Ids;

/**
 * @implements ProviderInterface<ProgressPhoto>
 */
final readonly class ProgressPhotoProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProgressPhoto
    {
        return ProgressPhoto::of($this->queryBus->ask(new GetProgressPhoto($this->currentUser->id(), self::photoId($uriVariables['id'] ?? null))));
    }

    public static function photoId(mixed $id): ProgressPhotoId
    {
        return Ids::parse($id, ProgressPhotoId::class, ProgressPhotoNotFound::withId(...));
    }
}

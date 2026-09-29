<?php

declare(strict_types=1);

namespace App\Coach\Presentation\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Coach\Application\Recommendation\GetRecommendation;
use App\Coach\Application\Recommendation\ListRecommendations;
use App\Coach\Domain\Exception\RecommendationNotFound;
use App\Coach\Domain\Recommendation\RecommendationId;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\Cursors;
use App\Shared\Presentation\Api\Ids;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<Recommendation>
 */
final readonly class RecommendationProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    /**
     * @return Recommendation|list<Recommendation>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Recommendation|array
    {
        $userId = $this->currentUser->id();
        if ($operation instanceof CollectionOperationInterface) {
            $request = $context['request'] ?? null;
            $query = $request instanceof Request ? $request->query : null;

            return array_map(Recommendation::of(...), $this->queryBus->ask(new ListRecommendations(
                $userId,
                $query?->getInt('limit', 20) ?? 20,
                Cursors::time($query?->getString('before'), 'before'),
                Cursors::id($query?->getString('beforeId'), 'beforeId'),
            )));
        }

        return Recommendation::of($this->queryBus->ask(new GetRecommendation(self::id($uriVariables['id'] ?? null), $userId)));
    }

    public static function id(mixed $id): RecommendationId
    {
        return Ids::parse($id, RecommendationId::class, static fn (string $value): RecommendationNotFound => RecommendationNotFound::because(\sprintf('No recommendation %s.', $value)));
    }
}

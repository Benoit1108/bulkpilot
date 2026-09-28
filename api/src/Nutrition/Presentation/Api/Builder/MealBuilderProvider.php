<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Builder;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Nutrition\Application\Builder\BuildMeal;
use App\Nutrition\Domain\Exception\InvalidMealSlot;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Nutrition\Presentation\Api\Shared\Days;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<MealBuilderResult>
 */
final readonly class MealBuilderProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MealBuilderResult
    {
        $request = $context['request'] ?? null;
        $query = $request instanceof Request ? $request->query : null;
        $day = Days::parse($query?->get('day'));
        $slot = (string) $query?->getString('slot');
        $mealSlot = '' === $slot ? null : MealSlot::tryFrom($slot) ?? throw InvalidMealSlot::named($slot);

        return MealBuilderResult::fromView($day->format('Y-m-d'), $this->queryBus->ask(new BuildMeal($this->currentUser->id(), $day, $mealSlot)));
    }
}

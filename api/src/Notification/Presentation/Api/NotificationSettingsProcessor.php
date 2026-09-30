<?php

declare(strict_types=1);

namespace App\Notification\Presentation\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Notification\Application\Settings\ChangeNotificationSettings;
use App\Notification\Application\Settings\GetNotificationSettings;
use App\Notification\Domain\Exception\InvalidNotificationSettings;
use App\Notification\Domain\Notification\Category;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Http\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<NotificationSettingsInput, NotificationSettings>
 */
final readonly class NotificationSettingsProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private RateLimiterFactoryInterface $notificationWritesLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NotificationSettings
    {
        $userId = $this->currentUser->id();
        RateLimit::consume($this->notificationWritesLimiter, 'notification_writes|'.$userId->value);

        $this->commandBus->dispatch(new ChangeNotificationSettings($userId, self::enabled($data->categories), $data->quietFrom, $data->quietTo));

        return NotificationSettings::of($this->queryBus->ask(new GetNotificationSettings($userId)));
    }

    /**
     * Every category exactly once, on or off: a category left out would be turned off unnoticed.
     *
     * @param array<mixed> $categories
     *
     * @return list<Category>
     */
    private static function enabled(array $categories): array
    {
        $known = array_map(static fn (Category $category): string => $category->value, Category::cases());
        $given = array_map(strval(...), array_keys($categories));
        sort($known);
        sort($given);
        if ($known !== $given || [] !== array_filter($categories, static fn (mixed $on): bool => !\is_bool($on))) {
            throw InvalidNotificationSettings::because('every category is given, on (true) or off (false)');
        }

        return array_values(array_filter(Category::cases(), static fn (Category $category): bool => true === $categories[$category->value]));
    }
}

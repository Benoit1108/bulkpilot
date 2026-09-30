<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Preferences;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\Preferences\ChangeTimeZone;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Http\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<TimeZoneInput, null>
 */
final readonly class TimeZoneProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus, private CurrentUser $currentUser, private RateLimiterFactoryInterface $preferenceWritesLimiter)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $userId = $this->currentUser->id();
        RateLimit::consume($this->preferenceWritesLimiter, 'preference_writes|'.$userId->value);
        $this->commandBus->dispatch(new ChangeTimeZone($userId, $data->timeZone));

        return null;
    }
}

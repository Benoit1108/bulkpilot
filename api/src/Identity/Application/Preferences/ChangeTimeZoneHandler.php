<?php

declare(strict_types=1);

namespace App\Identity\Application\Preferences;

use App\Identity\Domain\Preferences\Preferences;
use App\Identity\Domain\Preferences\PreferencesRepository;
use App\Identity\Domain\Preferences\TimeZone;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * The devices send their zone silently: it is a preference, never a change of the account (a
 * concurrent sign-in, email confirmation or password change is not disturbed).
 */
final readonly class ChangeTimeZoneHandler implements CommandHandler
{
    public function __construct(private PreferencesRepository $preferences, private ClockInterface $clock)
    {
    }

    public function __invoke(ChangeTimeZone $command): void
    {
        $now = $this->clock->now();
        $preferences = $this->preferences->of($command->userId) ?? Preferences::defaults($command->userId, $now);
        $preferences->moveToTimeZone(new TimeZone($command->timeZone), $now);
        $this->preferences->save($preferences);
    }
}

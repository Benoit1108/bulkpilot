<?php

declare(strict_types=1);

namespace App\Coach\Application\Settings;

use App\Coach\Domain\Settings\CoachSettingsRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * A new tone or pace applies from the next analysis: the recommendations already made keep their
 * wording (they are what was said).
 */
final readonly class ChangeCoachSettingsHandler implements CommandHandler
{
    public function __construct(private CoachSettingsRepository $settings, private ClockInterface $clock)
    {
    }

    public function __invoke(ChangeCoachSettings $command): void
    {
        $now = $this->clock->now();
        $settings = $this->settings->ofOrDefault($command->userId, $now);
        $settings->change($command->personality, $command->paceKgPerWeek, $now);
        $this->settings->save($settings);
    }
}

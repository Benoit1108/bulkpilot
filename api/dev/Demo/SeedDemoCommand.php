<?php

declare(strict_types=1);

namespace App\Dev\Demo;

use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Seeds a demo account (spec §134): registered for the dev and test environments only, and
 * refusing to run in production even if it were (ADR-0032).
 */
#[AsCommand(name: 'app:demo:seed', description: 'Creates a demo account with years of history (dev and test only).')]
final readonly class SeedDemoCommand
{
    public const int MAX_YEARS = 10;
    public const int MAX_ACCOUNTS = 50;

    public function __construct(
        private DemoSeeder $seeder,
        private ClockInterface $clock,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Years of history, ending yesterday.')]
        int $years = 3,
        #[Option('Days of history instead of years (small datasets, tests).')]
        ?int $days = null,
        #[Option('The demo account\'s email.')]
        string $email = DemoSeeder::EMAIL,
        #[Option('How many accounts: the first at that email, the others numbered (demo2@…), each its own history.')]
        int $accounts = 1,
        #[Option('Only the account of that number (several processes can then seed side by side).')]
        ?int $number = null,
    ): int {
        if ('prod' === $this->environment) {
            $io->error('Demo data never goes to production.');

            return Command::FAILURE;
        }
        $span = $days ?? 365 * $years;
        if ($span < 1 || $span > 365 * self::MAX_YEARS) {
            $io->error(\sprintf('Between 1 day and %d years of history.', self::MAX_YEARS));

            return Command::INVALID;
        }
        if ($accounts < 1 || $accounts > self::MAX_ACCOUNTS || (null !== $number && ($number < 1 || $number > self::MAX_ACCOUNTS))) {
            $io->error(\sprintf('Between 1 and %d accounts.', self::MAX_ACCOUNTS));

            return Command::INVALID;
        }

        foreach (null === $number ? range(1, $accounts) : [$number] as $each) {
            $address = self::numbered($email, $each);
            $io->progressStart($span);
            $result = $this->seeder->seed($address, $span, $this->clock->now(), static fn () => $io->progressAdvance(), $each - 1);
            $io->progressFinish();
            $io->success(\sprintf('%s (password « %s »): %d days, %d workouts.', $address, DemoSeeder::PASSWORD, $result['days'], $result['workouts']));
        }

        return Command::SUCCESS;
    }

    /** demo@bulkpilot.local, then demo2@bulkpilot.local, demo3@… */
    public static function numbered(string $email, int $number): string
    {
        return 1 === $number ? $email : (string) preg_replace('/@/', $number.'@', $email, 1);
    }
}

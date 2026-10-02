<?php

declare(strict_types=1);

namespace App\Tests\Integration\Demo;

use App\Dev\Demo\DemoSeeder;
use App\Dev\Demo\SeedDemoCommand;
use App\Kernel;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Demo data (spec §134, ADR-0032): built through the use cases, never in production.
 */
final class DemoSeedTest extends KernelTestCase
{
    public function testTheSeedBuildsAHistoryThroughTheUseCases(): void
    {
        self::bootKernel();
        $seeder = self::getContainer()->get(DemoSeeder::class);
        $today = new \DateTimeImmutable('2026-09-30 12:00:00 UTC');

        $result = $seeder->seed('demo-seed@bulkpilot.local', 21, $today);

        $connection = self::getContainer()->get(Connection::class);
        $count = static function (string $table) use ($connection, $result): int {
            $rows = $connection->fetchOne(\sprintf('SELECT count(*) FROM %s WHERE owner_id = ?', $table), [$result['userId']]);
            self::assertIsInt($rows);

            return $rows;
        };
        self::assertSame(21, $result['days']);
        self::assertGreaterThanOrEqual(9, $result['workouts'], 'About four sessions a week.');
        self::assertSame($result['workouts'], $count('workouts'));
        self::assertGreaterThan(15, $count('progression_weigh_ins'));
        self::assertGreaterThan(50, $count('journal_entries'));
        self::assertSame(3, $count('progression_measurement_days'), 'The Sundays.');
        self::assertGreaterThan(0, $count('coach_recommendations'));
        self::assertSame(1, $count('training_programs'));
        // The history ends yesterday: nothing is made up for a day not lived yet.
        self::assertSame('2026-09-29', $connection->fetchOne('SELECT max(day) FROM journal_entries WHERE owner_id = ?', [$result['userId']]));
    }

    public function testTheSameAddressIsSeededOnce(): void
    {
        self::bootKernel();
        $seeder = self::getContainer()->get(DemoSeeder::class);
        $seeder->seed('demo-once@bulkpilot.local', 1, new \DateTimeImmutable('2026-09-30 12:00:00 UTC'));

        $this->expectExceptionMessageMatches('/already exists/');
        $seeder->seed('demo-once@bulkpilot.local', 1, new \DateTimeImmutable('2026-09-30 12:00:00 UTC'));
    }

    public function testTheCommandRefusesToRunInProduction(): void
    {
        self::bootKernel();
        $command = new SeedDemoCommand(self::getContainer()->get(DemoSeeder::class), self::getContainer()->get(ClockInterface::class), 'prod');
        $output = new BufferedOutput();

        $status = $command(new SymfonyStyle(new ArrayInput([]), $output), days: 1);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('never goes to production', $output->fetch());
    }

    public function testTheProductionContainerHasNoDemoCommand(): void
    {
        $kernel = new Kernel('prod', false);
        $kernel->boot();
        try {
            self::assertFalse(new Application($kernel)->has('app:demo:seed'));
        } finally {
            $kernel->shutdown();
        }
    }
}

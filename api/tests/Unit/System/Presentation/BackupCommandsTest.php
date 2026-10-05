<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Presentation;

use App\System\Application\Backup\Backups;
use App\System\Application\Monitoring\Heartbeats;
use App\System\Domain\Backup\Retention;
use App\System\Presentation\Cli\CreateBackupCommand;
use App\System\Presentation\Cli\VerifyBackupCommand;
use App\Tests\Support\System\FakeDatabaseDump;
use App\Tests\Support\System\InMemoryBackupStore;
use App\Tests\Support\System\InMemoryBeats;
use App\Tests\Support\System\RecordingLogger;
use App\Tests\Support\System\RecordingPings;
use App\Tests\Support\System\ReversingBackupCipher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * What the CI and the operator rely on: a backup or a check that fails exits non-zero.
 */
final class BackupCommandsTest extends TestCase
{
    private FakeDatabaseDump $database;
    private InMemoryBackupStore $store;
    private Backups $backups;

    protected function setUp(): void
    {
        $this->database = new FakeDatabaseDump();
        $this->store = new InMemoryBackupStore();
        $clock = new MockClock('2026-09-30 02:30:00+00:00');
        $this->backups = new Backups($this->database, new ReversingBackupCipher(), $this->store, new Retention(30), $clock, new RecordingLogger(), new Heartbeats(new InMemoryBeats(), new RecordingPings(), $clock, new RecordingLogger()));
    }

    public function testABackupThenItsCheckSucceed(): void
    {
        self::assertSame(Command::SUCCESS, $this->execute(new CreateBackupCommand($this->backups), 'app:backup:create'));
        self::assertSame(Command::SUCCESS, $this->execute(new VerifyBackupCommand($this->backups), 'app:backup:verify', ['--min-accounts' => '2']));
    }

    public function testNoBackupFailsTheCheck(): void
    {
        self::assertSame(Command::FAILURE, $this->execute(new VerifyBackupCommand($this->backups), 'app:backup:verify'));
    }

    public function testACorruptedBackupFailsTheCheck(): void
    {
        $this->execute(new CreateBackupCommand($this->backups), 'app:backup:create');
        $this->store->objects['db/2026/09/30/023000.dump.enc'] = 'garbage';

        self::assertSame(Command::FAILURE, $this->execute(new VerifyBackupCommand($this->backups), 'app:backup:verify'));
    }

    public function testARestoreWithTooFewAccountsFailsTheCheck(): void
    {
        $this->execute(new CreateBackupCommand($this->backups), 'app:backup:create');

        self::assertSame(Command::FAILURE, $this->execute(new VerifyBackupCommand($this->backups), 'app:backup:verify', ['--min-accounts' => '3']));
    }

    public function testAFailedDumpFailsTheBackup(): void
    {
        $this->database->failing = true;

        self::assertSame(Command::FAILURE, $this->execute(new CreateBackupCommand($this->backups), 'app:backup:create'));
    }

    /**
     * @param array<string, string> $input
     */
    private function execute(CreateBackupCommand|VerifyBackupCommand $command, string $name, array $input = []): int
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find($name))->execute($input);
    }
}

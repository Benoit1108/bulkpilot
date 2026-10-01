<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Infrastructure;

use App\System\Application\Backup\BackupFailed;
use App\System\Infrastructure\Backup\PostgresDatabaseDump;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The real tools against the test database: a dump restores into a throwaway database that is
 * checked, then dropped.
 */
final class PostgresDatabaseDumpTest extends KernelTestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = (string) tempnam(sys_get_temp_dir(), 'dump-test');
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        parent::tearDown();
    }

    public function testADumpRestoresIntoADatabaseLikeTheLiveOne(): void
    {
        $dump = $this->dumpOfTheTestDatabase();

        $dump->dump($this->path);
        $check = $dump->restoreAndCheck($this->path, []);

        self::assertStringStartsWith('PGDMP', (string) file_get_contents($this->path, length: 5));
        self::assertGreaterThan(20, $check->tables);
        self::assertStringStartsWith('DoctrineMigrations\\', $check->migration);
        self::assertSame([], $this->scratchDatabases(), 'the throwaway database is dropped');
    }

    public function testSomethingElseDoesNotRestore(): void
    {
        file_put_contents($this->path, 'not a dump');

        try {
            $this->dumpOfTheTestDatabase()->restoreAndCheck($this->path, []);
            self::fail('The restore should have failed.');
        } catch (BackupFailed $failure) {
            self::assertStringStartsWith('pg_restore failed', $failure->getMessage());
        }
        self::assertSame([], $this->scratchDatabases(), 'the throwaway database is dropped even then');
    }

    public function testAnUnreachableServerFailsTheDump(): void
    {
        $this->expectExceptionMessage('pg_dump failed');

        new PostgresDatabaseDump('postgresql://app:app@127.0.0.1:1/none')->dump($this->path);
    }

    private function dumpOfTheTestDatabase(): PostgresDatabaseDump
    {
        // DATABASE_URL names the application's database; the tests run on its `_test` twin.
        $url = $_SERVER['DATABASE_URL'] ?? getenv('DATABASE_URL');
        self::assertIsString($url);

        return new PostgresDatabaseDump((string) preg_replace('#/[^/?]+(\?|$)#', '/'.$this->connection()->getDatabase().'$1', $url, 1));
    }

    /**
     * @return list<string>
     */
    private function scratchDatabases(): array
    {
        /** @var list<string> */
        return $this->connection()->fetchFirstColumn("SELECT datname FROM pg_database WHERE datname LIKE '%\\_restore\\_check\\_%'");
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }
}

<?php

declare(strict_types=1);

namespace App\System\Infrastructure\Backup;

use App\System\Application\Backup\BackupFailed;
use App\System\Application\Backup\DatabaseDump;
use App\System\Application\Backup\RestoreCheck;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * pg_dump / pg_restore of the server's own major version (the image ships the matching client).
 * The test restore goes into a database created for it and always dropped after; the account
 * must be allowed to create databases (the image's default user is).
 */
final readonly class PostgresDatabaseDump implements DatabaseDump
{
    private const int TIMEOUT_SECONDS = 3600;

    public function __construct(#[Autowire('%env(DATABASE_URL)%')] private string $databaseUrl)
    {
    }

    public function dump(string $path): void
    {
        $this->run('pg_dump', '--format=custom', '--no-owner', '--no-privileges', '--dbname='.$this->database(), '--file='.$path);
    }

    public function restoreAndCheck(string $path, array $erasedIds): RestoreCheck
    {
        $live = $this->database();
        $scratch = $live.'_restore_check_'.bin2hex(random_bytes(4));
        $this->run('createdb', '--maintenance-db=postgres', $scratch);
        try {
            $this->run('pg_restore', '--no-owner', '--no-privileges', '--exit-on-error', '--dbname='.$scratch, $path);

            return $this->check($scratch, $live, $erasedIds);
        } finally {
            $this->run('dropdb', '--maintenance-db=postgres', '--if-exists', $scratch);
        }
    }

    /**
     * The restored database must be the live one as it was: its tables all live ones (the same
     * set at the same migration), its last migration one the live database ran, accounts readable.
     */
    /**
     * @param list<string> $erasedIds
     */
    private function check(string $restored, string $live, array $erasedIds): RestoreCheck
    {
        $tables = "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY 1";
        $migration = 'SELECT version FROM doctrine_migration_versions ORDER BY version DESC LIMIT 1';
        $restoredTables = $this->lines($restored, $tables);
        $liveTables = $this->lines($live, $tables);
        $restoredMigration = $this->lines($restored, $migration)[0] ?? throw BackupFailed::because('the restored database has no migration');
        $liveMigration = $this->lines($live, $migration)[0] ?? '';

        if (!\in_array('users', $restoredTables, true) || [] !== array_diff($restoredTables, $liveTables)) {
            throw BackupFailed::because('the restored tables are not the live ones');
        }
        if ($restoredMigration === $liveMigration && $restoredTables !== $liveTables) {
            throw BackupFailed::because('tables are missing from the restored database');
        }
        if ([] === $this->lines($live, \sprintf("SELECT 1 FROM doctrine_migration_versions WHERE version = '%s'", str_replace("'", "''", $restoredMigration)))) {
            throw BackupFailed::because(\sprintf('the restored migration %s is unknown to the live database', $restoredMigration));
        }

        $rows = [];
        foreach (DatabaseDump::KEY_TABLES as $table) {
            if (\in_array($table, $restoredTables, true)) {
                $rows[$table] = (int) ($this->lines($restored, \sprintf('SELECT count(*) FROM %s', $table))[0] ?? 0);
            }
        }
        $erased = array_values(array_filter($erasedIds, static fn (string $id): bool => 1 === preg_match('/^[0-9a-f-]{36}$/', $id)));
        $toReapply = [] === $erased ? 0 : (int) ($this->lines($restored, \sprintf("SELECT count(*) FROM users WHERE id IN ('%s')", implode("', '", $erased)))[0] ?? 0);

        return new RestoreCheck(\count($restoredTables), $restoredMigration, $rows, $toReapply);
    }

    /**
     * @return list<string>
     */
    private function lines(string $database, string $sql): array
    {
        $output = $this->run('psql', '--no-psqlrc', '--tuples-only', '--no-align', '--set=ON_ERROR_STOP=1', '--dbname='.$database, '--command='.$sql);

        return array_values(array_filter(array_map(trim(...), explode("\n", $output)), static fn (string $line): bool => '' !== $line));
    }

    /**
     * Runs a PostgreSQL client tool against the server of DATABASE_URL.
     */
    private function run(string $tool, string ...$arguments): string
    {
        $url = $this->url();
        $process = new Process(
            [$tool, '--host='.$url['host'], '--port='.$url['port'], '--username='.$url['user'], ...$arguments],
            null,
            // The password never appears in the process list; TLS settings of DATABASE_URL are kept.
            ['PGPASSWORD' => $url['pass'], 'PGCONNECT_TIMEOUT' => '10', ...$url['tls']],
            null,
            self::TIMEOUT_SECONDS,
        );
        try {
            $process->mustRun();
        } catch (ProcessFailedException $failure) {
            throw BackupFailed::because(\sprintf('%s failed: %s', $tool, trim($process->getErrorOutput())), $failure);
        }

        return $process->getOutput();
    }

    private function database(): string
    {
        return $this->url()['name'];
    }

    /**
     * @return array{host: string, port: string, user: string, pass: string, name: string, tls: array<string, string>}
     */
    private function url(): array
    {
        $parts = parse_url($this->databaseUrl);
        if (false === $parts || !isset($parts['host'], $parts['user'], $parts['path'])) {
            throw BackupFailed::because('DATABASE_URL cannot be read');
        }

        parse_str($parts['query'] ?? '', $query);
        $tls = [];
        foreach (['sslmode' => 'PGSSLMODE', 'sslrootcert' => 'PGSSLROOTCERT', 'sslcert' => 'PGSSLCERT', 'sslkey' => 'PGSSLKEY'] as $parameter => $variable) {
            if (\is_string($query[$parameter] ?? null) && '' !== $query[$parameter]) {
                $tls[$variable] = $query[$parameter];
            }
        }

        return [
            'tls' => $tls,
            'host' => $parts['host'],
            'port' => (string) ($parts['port'] ?? 5432),
            'user' => rawurldecode($parts['user']),
            'pass' => rawurldecode($parts['pass'] ?? ''),
            'name' => ltrim($parts['path'], '/'),
        ];
    }
}

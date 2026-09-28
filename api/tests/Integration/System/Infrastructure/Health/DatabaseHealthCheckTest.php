<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Infrastructure\Health;

use App\System\Infrastructure\Health\DatabaseHealthCheck;
use Doctrine\DBAL\DriverManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DatabaseHealthCheckTest extends KernelTestCase
{
    public function testTheConfiguredDatabaseIsReachable(): void
    {
        $check = self::getContainer()->get(DatabaseHealthCheck::class);

        self::assertSame('database', $check->name());
        self::assertTrue($check->isHealthy());
    }

    public function testAnUnreachableDatabaseIsReportedDown(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => '127.0.0.1',
            'port' => 1,
            'user' => 'nobody',
            'password' => 'nothing',
            'dbname' => 'none',
        ]);

        self::assertFalse(new DatabaseHealthCheck($connection)->isHealthy());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Application\Health;

use App\System\Application\Health\GetHealth;
use App\System\Application\Health\GetHealthHandler;
use App\System\Application\Health\HealthCheck;
use PHPUnit\Framework\TestCase;

final class GetHealthHandlerTest extends TestCase
{
    public function testAllChecksUpMeansHealthy(): void
    {
        $report = new GetHealthHandler([self::check('database', true), self::check('storage', true)])(new GetHealth());

        self::assertTrue($report->isHealthy());
        self::assertSame(['database' => true, 'storage' => true], $report->checks);
    }

    public function testOneCheckDownMeansDegraded(): void
    {
        $report = new GetHealthHandler([self::check('database', false), self::check('storage', true)])(new GetHealth());

        self::assertFalse($report->isHealthy());
        self::assertSame(['database' => false, 'storage' => true], $report->checks);
    }

    public function testNoCheckIsHealthy(): void
    {
        self::assertTrue(new GetHealthHandler([])(new GetHealth())->isHealthy());
    }

    /**
     * @param non-empty-string $name
     */
    private static function check(string $name, bool $healthy): HealthCheck
    {
        return new readonly class($name, $healthy) implements HealthCheck {
            /**
             * @param non-empty-string $name
             */
            public function __construct(private string $name, private bool $healthy)
            {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function isHealthy(): bool
            {
                return $this->healthy;
            }
        };
    }
}

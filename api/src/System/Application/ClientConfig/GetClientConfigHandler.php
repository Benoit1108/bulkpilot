<?php

declare(strict_types=1);

namespace App\System\Application\ClientConfig;

use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetClientConfigHandler implements QueryHandler
{
    /**
     * @param string $dsn empty when the PWA reports no error (development, tests)
     */
    public function __construct(private string $dsn, private string $environment, private string $release)
    {
    }

    public function __invoke(GetClientConfig $query): ClientConfig
    {
        return new ClientConfig('' === $this->dsn ? null : $this->dsn, $this->environment, $this->release);
    }
}

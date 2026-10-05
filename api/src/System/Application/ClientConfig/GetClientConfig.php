<?php

declare(strict_types=1);

namespace App\System\Application\ClientConfig;

use App\Shared\Application\Bus\Query\Query;

/**
 * @implements Query<ClientConfig>
 */
final readonly class GetClientConfig implements Query
{
}

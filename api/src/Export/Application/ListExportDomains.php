<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Shared\Application\Bus\Query\Query;

/**
 * @implements Query<list<ExportDomainView>>
 */
final readonly class ListExportDomains implements Query
{
}

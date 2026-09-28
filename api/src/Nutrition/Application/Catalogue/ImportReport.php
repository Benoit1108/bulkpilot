<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Catalogue;

final class ImportReport
{
    public int $created = 0;
    public int $updated = 0;

    /** @var array<string, string> reference => reason */
    public array $skipped = [];
}

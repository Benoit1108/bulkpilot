<?php

declare(strict_types=1);

namespace App\Coach\Infrastructure\Persistence\Doctrine\Type;

use App\Coach\Domain\Report\WeeklyReportId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

final class WeeklyReportIdType extends UuidType
{
    public const string NAME = 'weekly_report_id';

    protected function identifierClass(): string
    {
        return WeeklyReportId::class;
    }
}

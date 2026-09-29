<?php

declare(strict_types=1);

namespace App\Coach\Application\Analysis;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * The Coach's page: the recommendations offered, the week's adherence and the last report.
 *
 * @implements Query<CoachView>
 */
final readonly class GetCoach implements Query
{
    public function __construct(public UserId $userId)
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Export\Presentation\Api;

use Symfony\Component\Validator\Constraints as Assert;

final class ExportInput
{
    /** First day (YYYY-MM-DD); none for everything before `to`. */
    #[Assert\Date]
    public ?string $from = null;

    /** Last day (YYYY-MM-DD); none for everything after `from`. */
    #[Assert\Date]
    public ?string $to = null;

    /** @var list<string> the domains (GET /exports/domains); none for all of them */
    #[Assert\Count(max: 20)]
    #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 32)])]
    public array $domains = [];
}

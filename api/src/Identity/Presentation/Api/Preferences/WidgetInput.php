<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Preferences;

use Symfony\Component\Validator\Constraints as Assert;

final class WidgetInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 32)]
    public string $key = '';

    #[Assert\NotNull]
    public ?bool $visible = null;
}

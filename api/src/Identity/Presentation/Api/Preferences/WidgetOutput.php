<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Preferences;

use ApiPlatform\Metadata\ApiProperty;
use App\Identity\Application\Preferences\WidgetView;

final readonly class WidgetOutput
{
    public function __construct(
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['weight', 'nutrition', 'meals', 'workout', 'coach', 'goal', 'records', 'report']])]
        public string $key,
        #[ApiProperty(required: true)]
        public bool $visible,
    ) {
    }

    public static function of(WidgetView $view): self
    {
        return new self($view->key, $view->visible);
    }
}

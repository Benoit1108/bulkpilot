<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Builder;

use ApiPlatform\Metadata\ApiProperty;
use App\Nutrition\Application\Builder\ProposalItemView;
use App\Nutrition\Presentation\Api\Shared\Nutrients;

final readonly class ProposalItem
{
    public function __construct(
        #[ApiProperty(required: true)]
        public string $foodId,
        #[ApiProperty(required: true)]
        public string $name,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['protein', 'carbohydrate', 'fat', 'side']])]
        public string $role,
        #[ApiProperty(required: true)]
        public float $grams,
        #[ApiProperty(required: true)]
        public Nutrients $nutrients,
    ) {
    }

    public static function of(ProposalItemView $view): self
    {
        return new self($view->foodId, $view->name, $view->role->value, $view->grams, Nutrients::of($view->amounts));
    }
}

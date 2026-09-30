<?php

declare(strict_types=1);

namespace App\Notification\Domain\Check;

use App\Notification\Domain\Notification\Category;

/**
 * A notification a rule wants to make. Its key says what it is about: one notification per key,
 * ever (spec §77: nothing redundant).
 */
final readonly class Draft
{
    public function __construct(
        public Category $category,
        public string $key,
        public string $title,
        public string $body,
        public ?string $link,
    ) {
    }
}

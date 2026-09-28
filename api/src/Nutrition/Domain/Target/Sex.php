<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Target;

/**
 * Biological sex for the Mifflin-St Jeor equation. Nutrition keeps its own copy of the value it
 * computed with (bounded contexts do not read each other's data).
 */
enum Sex: string
{
    case Female = 'female';
    case Male = 'male';
}

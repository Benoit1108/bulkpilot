<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

/**
 * Biological sex, used by energy expenditure formulas (Nutrition).
 */
enum Sex: string
{
    case Female = 'female';
    case Male = 'male';
}

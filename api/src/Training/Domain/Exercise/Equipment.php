<?php

declare(strict_types=1);

namespace App\Training\Domain\Exercise;

enum Equipment: string
{
    case Barbell = 'barbell';
    case Dumbbell = 'dumbbell';
    case EzBar = 'ez_bar';
    case Machine = 'machine';
    case Cable = 'cable';
    case SmithMachine = 'smith_machine';
    case Kettlebell = 'kettlebell';
    case Band = 'band';
    case Bodyweight = 'bodyweight';
    case Other = 'other';
}

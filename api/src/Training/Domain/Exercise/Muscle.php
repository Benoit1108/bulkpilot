<?php

declare(strict_types=1);

namespace App\Training\Domain\Exercise;

enum Muscle: string
{
    case Chest = 'chest';
    case Lats = 'lats';
    case UpperBack = 'upper_back';
    case Traps = 'traps';
    case LowerBack = 'lower_back';
    case FrontDelts = 'front_delts';
    case SideDelts = 'side_delts';
    case RearDelts = 'rear_delts';
    case Biceps = 'biceps';
    case Triceps = 'triceps';
    case Forearms = 'forearms';
    case Abs = 'abs';
    case Obliques = 'obliques';
    case Glutes = 'glutes';
    case Quads = 'quads';
    case Hamstrings = 'hamstrings';
    case Adductors = 'adductors';
    case Calves = 'calves';
}

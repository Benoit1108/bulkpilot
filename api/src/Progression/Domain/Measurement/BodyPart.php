<?php

declare(strict_types=1);

namespace App\Progression\Domain\Measurement;

/**
 * The measures everyone has (spec §54), in the order a tape goes round the body.
 */
enum BodyPart: string
{
    case Neck = 'neck';
    case Shoulders = 'shoulders';
    case Chest = 'chest';
    case LeftArm = 'left_arm';
    case RightArm = 'right_arm';
    case Waist = 'waist';
    case LeftThigh = 'left_thigh';
    case RightThigh = 'right_thigh';
    case LeftCalf = 'left_calf';
    case RightCalf = 'right_calf';
}

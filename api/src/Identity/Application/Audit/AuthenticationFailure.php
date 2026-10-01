<?php

declare(strict_types=1);

namespace App\Identity\Application\Audit;

/**
 * A proof of identity refused for a known account: the owner sees it in their activity.
 */
enum AuthenticationFailure: string
{
    /** A wrong password at sign-in. */
    case SignIn = 'identity.sign_in_failed';
    /** A wrong second-factor code at sign-in. */
    case SecondFactor = 'identity.second_factor_rejected';
    /** A wrong password or code confirming a sensitive action (the account's deletion). */
    case Reauthentication = 'identity.reauthentication_failed';
}

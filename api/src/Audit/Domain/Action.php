<?php

declare(strict_types=1);

namespace App\Audit\Domain;

/**
 * Every important action the journal keeps (spec §10, §105), `context.what`. A new one is listed
 * here, with its wording: the journal refuses one it does not know.
 */
enum Action: string
{
    case Registered = 'identity.registered';
    case EmailVerified = 'identity.email_verified';
    case SignedIn = 'identity.signed_in';
    case SignInFailed = 'identity.sign_in_failed';
    case SecondFactorRejected = 'identity.second_factor_rejected';
    case ReauthenticationFailed = 'identity.reauthentication_failed';
    case PasswordChanged = 'identity.password_changed';
    case PasswordReset = 'identity.password_reset';
    case TwoFactorEnabled = 'identity.two_factor_enabled';
    case TwoFactorDisabled = 'identity.two_factor_disabled';
    case RecoveryCodeUsed = 'identity.recovery_code_used';
    case RecoveryCodesRegenerated = 'identity.recovery_codes_regenerated';
    case ExternalIdentityLinked = 'identity.external_identity_linked';
    case SessionRevoked = 'identity.session_revoked';
    case DeletionScheduled = 'identity.deletion_scheduled';
    case DeletionCancelled = 'identity.deletion_cancelled';
    case AccountErased = 'identity.account_erased';
    case TargetSet = 'nutrition.target_set';
    case RecommendationDecided = 'coach.recommendation_decided';
    case ExportRequested = 'export.requested';
    case ExportDownloaded = 'export.downloaded';

    /** Every value, for the API schema (a test keeps it in step with the cases). */
    public const array VALUES = [
        'identity.registered',
        'identity.email_verified',
        'identity.signed_in',
        'identity.sign_in_failed',
        'identity.second_factor_rejected',
        'identity.reauthentication_failed',
        'identity.password_changed',
        'identity.password_reset',
        'identity.two_factor_enabled',
        'identity.two_factor_disabled',
        'identity.recovery_code_used',
        'identity.recovery_codes_regenerated',
        'identity.external_identity_linked',
        'identity.session_revoked',
        'identity.deletion_scheduled',
        'identity.deletion_cancelled',
        'identity.account_erased',
        'nutrition.target_set',
        'coach.recommendation_decided',
        'export.requested',
        'export.downloaded',
    ];
}

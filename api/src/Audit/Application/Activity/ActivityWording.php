<?php

declare(strict_types=1);

namespace App\Audit\Application\Activity;

use App\Audit\Domain\Action;
use App\Audit\Domain\Source;

/**
 * The journal in words, as the export writes it (the PWA words it the same way): what happened,
 * who did it, and its details readable (« 2 350 → 2 500 kcal, à partir du 2026-10-01, par le Coach »).
 */
final class ActivityWording
{
    private const array LABELS = [
        'identity.registered' => 'Création du compte',
        'identity.email_verified' => 'Adresse email confirmée',
        'identity.signed_in' => 'Connexion',
        'identity.sign_in_failed' => 'Connexion refusée (mauvais mot de passe)',
        'identity.second_factor_rejected' => 'Code de double authentification refusé',
        'identity.reauthentication_failed' => 'Confirmation refusée (mauvais mot de passe ou code)',
        'identity.password_changed' => 'Mot de passe changé',
        'identity.password_reset' => 'Mot de passe réinitialisé',
        'identity.two_factor_enabled' => 'Double authentification activée',
        'identity.two_factor_disabled' => 'Double authentification désactivée',
        'identity.recovery_code_used' => 'Code de secours utilisé',
        'identity.recovery_codes_regenerated' => 'Codes de secours régénérés',
        'identity.external_identity_linked' => 'Connexion externe liée',
        'identity.session_revoked' => 'Appareil déconnecté',
        'identity.deletion_scheduled' => 'Suppression du compte demandée',
        'identity.deletion_cancelled' => 'Suppression du compte annulée',
        'identity.account_erased' => 'Compte supprimé',
        'nutrition.target_set' => 'Objectif nutritionnel fixé',
        'coach.recommendation_decided' => 'Recommandation du Coach',
        'export.requested' => 'Export demandé',
        'export.downloaded' => 'Lien de téléchargement de l’export créé',
    ];
    private const array SOURCES = [Source::USER => 'Toi', Source::COACH => 'Le Coach', Source::SYSTEM => 'BulkPilot'];
    private const array ORIGINS = ['estimate' => 'depuis l’estimation', 'manual' => 'saisi à la main', 'coach' => 'par le Coach'];
    private const array DECISIONS = ['accepted' => 'acceptée', 'rejected' => 'refusée', 'postponed' => 'reportée', 'pending' => 'en attente', 'expired' => 'expirée'];

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }

    public static function source(string $source): string
    {
        return self::SOURCES[$source] ?? $source;
    }

    /**
     * @param array<string, string|int|float|bool|null> $details
     */
    public static function details(string $action, array $details): ?string
    {
        $text = static fn (string $key): ?string => isset($details[$key]) ? (string) $details[$key] : null;
        $kcal = static fn (?string $value): string => null === $value ? '' : number_format((float) $value, 0, ',', "\u{202F}").' kcal';

        $parts = match (Action::tryFrom($action)) {
            Action::TargetSet => [
                (null === $text('previous_kcal') ? '' : $kcal($text('previous_kcal')).' → ').$kcal($text('kcal')),
                null === $text('effective_from') ? null : 'à partir du '.$text('effective_from'),
                self::ORIGINS[$text('origin') ?? ''] ?? null,
            ],
            Action::RecommendationDecided => [$text('kind'), self::DECISIONS[$text('status') ?? ''] ?? $text('status')],
            Action::SignedIn, Action::SessionRevoked => [$text('device')],
            Action::RecoveryCodeUsed => [null === $text('remaining') ? null : $text('remaining').' restant(s)'],
            Action::ExternalIdentityLinked => [null === $text('provider') ? null : ucfirst($text('provider'))],
            Action::DeletionScheduled => [null === $text('due_on') ? null : 'effacé le '.$text('due_on')],
            Action::ExportRequested => [
                match (true) {
                    null === $text('from') && null === $text('to') => 'toutes les dates',
                    default => \sprintf('du %s au %s', $text('from') ?? 'début', $text('to') ?? 'aujourd’hui'),
                },
                'all' === $text('domains') || null === $text('domains') ? 'toutes les données' : $text('domains'),
            ],
            default => [],
        };
        $parts = array_values(array_filter($parts, static fn (?string $part): bool => null !== $part && '' !== $part));

        return [] === $parts ? null : implode(', ', $parts);
    }
}

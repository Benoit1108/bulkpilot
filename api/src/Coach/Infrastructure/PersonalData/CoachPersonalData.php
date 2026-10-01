<?php

declare(strict_types=1);

namespace App\Coach\Infrastructure\PersonalData;

use App\Shared\Application\PersonalData\Dataset;
use App\Shared\Application\PersonalData\ExportScope;
use App\Shared\Application\PersonalData\PersonalData;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Infrastructure\Persistence\Doctrine\PersonalRows as R;
use Doctrine\DBAL\Connection;

/**
 * The Coach's recommendations and decisions, weekly reports and settings (ADR-0030).
 */
final readonly class CoachPersonalData implements PersonalData
{
    public const string DOMAIN = 'coach';
    private const array KINDS = [
        'calories.no_target' => 'Pas d’objectif calorique', 'calories.no_pace' => 'Pas de rythme visé', 'calories.increase' => 'Augmenter les calories',
        'calories.decrease' => 'Baisser les calories', 'calories.reach_target_first' => 'Atteindre d’abord l’objectif', 'calories.back_to_target' => 'Revenir à l’objectif',
        'protein.low' => 'Protéines basses', 'training.stall' => 'Stagnation', 'training.missed_sessions' => 'Séances manquées',
        'journal.incomplete' => 'Journal incomplet', 'weigh_ins.few' => 'Pesées trop rares', 'training.records' => 'Records',
    ];
    private const array LEVELS = ['priority' => 'Prioritaire', 'watch' => 'À surveiller', 'info' => 'Information'];
    private const array STATUSES = ['pending' => 'En attente', 'accepted' => 'Acceptée', 'rejected' => 'Refusée', 'postponed' => 'Reportée', 'expired' => 'Expirée'];
    private const array VERDICTS = ['on_track' => 'Conforme', 'below' => 'En dessous du rythme', 'above' => 'Au-dessus du rythme', 'unknown' => 'Pas assez de données'];
    private const array PERSONALITIES = ['direct' => 'Direct', 'encouraging' => 'Encourageant', 'neutral' => 'Neutre'];
    private const array PROVIDERS = ['fake' => 'Gabarits (sans IA)'];

    public function __construct(private Connection $connection)
    {
    }

    public function domain(): string
    {
        return self::DOMAIN;
    }

    public function title(): string
    {
        return 'Coach';
    }

    public function periodFiltered(): bool
    {
        return true;
    }

    public function erasedLast(): bool
    {
        return false;
    }

    public function export(UserId $userId, ExportScope $scope): array
    {
        $c = $this->connection;
        $recommendations = R::rows($c, 'SELECT created_at, kind, level, title, body, why, proposal_from_kcal, proposal_by_kcal, status, decided_at, provider FROM coach_recommendations WHERE owner_id = :user', $userId, $scope, 'created_at', moment: true, order: 'created_at');
        $reports = R::rows($c, 'SELECT week_start, figures, verdict, action FROM coach_weekly_reports WHERE owner_id = :user', $userId, $scope, 'week_start', order: 'week_start');
        $settings = R::rows($c, 'SELECT personality, pace_kg_per_week, updated_at FROM coach_settings WHERE owner_id = :user', $userId);

        return [
            new Dataset('recommandations', 'Recommandations', ['Faite le', 'Type', 'Niveau', 'Titre', 'Message', 'Pourquoi', 'Objectif de départ (kcal)', 'Changement proposé (kcal)', 'Statut', 'Décidée le', 'Rédigée par'], array_map(static fn (array $row): array => [
                R::time($row['created_at']), R::label($row['kind'], self::KINDS), R::label($row['level'], self::LEVELS), R::string($row['title']), R::string($row['body']),
                implode(' / ', array_filter(R::json($row['why']), \is_string(...))), R::int($row['proposal_from_kcal']), R::int($row['proposal_by_kcal']),
                R::label($row['status'], self::STATUSES), R::time($row['decided_at']), R::label($row['provider'], self::PROVIDERS),
            ], $recommendations)),
            new Dataset('rapports', 'Rapports de la semaine', ['Semaine du', 'Poids au début (kg)', 'Poids à la fin (kg)', 'Calories moyennes (kcal)', 'Protéines moyennes (g)', 'Jours saisis', 'Séances', 'Records', 'Verdict', 'Action'], array_map(static function (array $row): array {
                $figures = R::json($row['figures']);

                return [
                    R::string($row['week_start']), R::float($figures['startKg'] ?? null), R::float($figures['endKg'] ?? null), R::float($figures['averageKcal'] ?? null), R::float($figures['averageProtein'] ?? null),
                    R::int($figures['loggedDays'] ?? null), R::int($figures['sessions'] ?? null), R::int($figures['records'] ?? null), R::label($row['verdict'], self::VERDICTS), R::string($row['action']),
                ];
            }, $reports)),
            new Dataset('reglages', 'Réglages du Coach', ['Ton', 'Rythme visé (kg/semaine)', 'Modifiés le'], array_map(static fn (array $row): array => [
                R::label($row['personality'], self::PERSONALITIES), R::float($row['pace_kg_per_week']), R::time($row['updated_at']),
            ], $settings)),
        ];
    }

    public function files(UserId $userId, ExportScope $scope): array
    {
        return [];
    }

    public function erase(UserId $userId): void
    {
        R::deleteOwned($this->connection, $userId, 'owner_id', 'coach_recommendations', 'coach_weekly_reports', 'coach_settings');
    }
}

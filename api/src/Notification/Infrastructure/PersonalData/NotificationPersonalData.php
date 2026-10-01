<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\PersonalData;

use App\Shared\Application\PersonalData\Dataset;
use App\Shared\Application\PersonalData\ExportScope;
use App\Shared\Application\PersonalData\PersonalData;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Infrastructure\Persistence\Doctrine\PersonalRows as R;
use Doctrine\DBAL\Connection;

/**
 * The notifications received and their settings (ADR-0030).
 */
final readonly class NotificationPersonalData implements PersonalData
{
    public const string DOMAIN = 'notification';
    private const array CATEGORIES = [
        'low_calories' => 'Calories très insuffisantes', 'workout_not_started' => 'Séance prévue non commencée', 'missing_weigh_in' => 'Pesée manquante',
        'goal_reached' => 'Objectif atteint', 'record' => 'Record', 'weekly_report' => 'Rapport de la semaine',
    ];

    public function __construct(private Connection $connection)
    {
    }

    public function domain(): string
    {
        return self::DOMAIN;
    }

    public function title(): string
    {
        return 'Notifications';
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
        $notifications = R::rows($this->connection, 'SELECT created_at, category, title, body, read_at FROM notifications WHERE owner_id = :user', $userId, $scope, 'created_at', moment: true, order: 'created_at');
        $settings = R::rows($this->connection, 'SELECT disabled, quiet_from, quiet_to, updated_at FROM notification_settings WHERE owner_id = :user', $userId);

        return [
            new Dataset('notifications', 'Notifications', ['Reçue le', 'Catégorie', 'Titre', 'Message', 'Lue le'], array_map(static fn (array $row): array => [
                R::time($row['created_at']), R::label($row['category'], self::CATEGORIES), R::string($row['title']), R::string($row['body']), R::time($row['read_at']),
            ], $notifications)),
            new Dataset('reglages', 'Réglages des notifications', ['Catégories désactivées', 'Horaires silencieux de', 'à', 'Modifiés le'], array_map(static fn (array $row): array => [
                implode(', ', array_map(static fn (mixed $category): string => (string) R::label($category, self::CATEGORIES), array_filter(R::json($row['disabled']), \is_string(...)))),
                R::string($row['quiet_from']), R::string($row['quiet_to']), R::time($row['updated_at']),
            ], $settings)),
        ];
    }

    public function files(UserId $userId, ExportScope $scope): array
    {
        return [];
    }

    public function erase(UserId $userId): void
    {
        R::deleteOwned($this->connection, $userId, 'owner_id', 'notifications', 'notification_settings', 'notification_checks');
    }
}

<?php

declare(strict_types=1);

namespace App\Progression\Infrastructure\PersonalData;

use App\Shared\Application\PersonalData\Dataset;
use App\Shared\Application\PersonalData\ExportFile;
use App\Shared\Application\PersonalData\ExportScope;
use App\Shared\Application\PersonalData\PersonalData;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Infrastructure\Persistence\Doctrine\PersonalRows as R;
use Doctrine\DBAL\Connection;

/**
 * Weigh-ins, weight goals, measures and measurements, and the photos (their files come with the
 * export under a readable name; Media erases them with the account) (ADR-0030).
 */
final readonly class ProgressionPersonalData implements PersonalData
{
    public const string DOMAIN = 'progression';
    private const array PARTS = [
        'neck' => 'Cou', 'shoulders' => 'Épaules', 'chest' => 'Poitrine', 'left_arm' => 'Bras gauche', 'right_arm' => 'Bras droit',
        'waist' => 'Tour de taille', 'left_thigh' => 'Cuisse gauche', 'right_thigh' => 'Cuisse droite', 'left_calf' => 'Mollet gauche', 'right_calf' => 'Mollet droit',
    ];
    private const array POSES = ['front' => 'face', 'side' => 'profil', 'back' => 'dos', 'other' => 'autre'];

    public function __construct(private Connection $connection)
    {
    }

    public function domain(): string
    {
        return self::DOMAIN;
    }

    public function title(): string
    {
        return 'Progression';
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
        $weighIns = R::rows($c, 'SELECT day, weight_kg, recorded_at FROM progression_weigh_ins WHERE owner_id = :user', $userId, $scope, 'day', order: 'day');
        $goals = R::rows($c, 'SELECT start_day, start_kg, target_kg, milestones, set_at, ended_at FROM progression_weight_goals WHERE owner_id = :user', $userId, order: 'set_at');
        $measures = R::rows($c, 'SELECT id, name, created_at, archived_at FROM progression_custom_measures WHERE owner_id = :user', $userId, order: 'created_at');
        $days = R::rows($c, 'SELECT day, measurements FROM progression_measurement_days WHERE owner_id = :user', $userId, $scope, 'day', order: 'day');
        $names = self::PARTS;
        foreach ($measures as $measure) {
            $names[(string) R::string($measure['id'])] = (string) R::string($measure['name']);
        }

        return [
            new Dataset('pesees', 'Pesées', ['Jour', 'Poids (kg)', 'Enregistrée le'], array_map(static fn (array $row): array => [
                R::string($row['day']), R::float($row['weight_kg']), R::time($row['recorded_at']),
            ], $weighIns)),
            new Dataset('objectifs_poids', 'Objectifs de poids', ['Départ le', 'Poids de départ (kg)', 'Cible (kg)', 'Jalons (kg)', 'Fixé le', 'Terminé le'], array_map(static fn (array $row): array => [
                R::string($row['start_day']), R::float($row['start_kg']), R::float($row['target_kg']),
                implode(', ', array_map(R::decimal(...), R::json($row['milestones']))), R::time($row['set_at']), R::time($row['ended_at']),
            ], $goals)),
            new Dataset('mesures_perso', 'Mesures personnelles', ['Nom', 'Créée le', 'Archivée le'], array_map(static fn (array $row): array => [
                R::string($row['name']), R::time($row['created_at']), R::time($row['archived_at']),
            ], $measures)),
            new Dataset('mensurations', 'Mensurations', ['Jour', 'Mesure', 'Valeur (cm)'], self::measurements($days, $names)),
            new Dataset('photos', 'Photos', ['Jour', 'Pose', 'Largeur (px)', 'Hauteur (px)', 'Ajoutée le', 'Fichier'], array_map(static fn (array $photo): array => [
                $photo['day'], self::POSES[$photo['pose']] ?? $photo['pose'], $photo['width'], $photo['height'], $photo['addedAt'], $photo['path'],
            ], $this->photos($userId, $scope))),
        ];
    }

    public function files(UserId $userId, ExportScope $scope): array
    {
        return array_map(static fn (array $photo): ExportFile => new ExportFile(new MediaId($photo['mediaId']), $photo['path']), $this->photos($userId, $scope));
    }

    public function erase(UserId $userId): void
    {
        R::deleteOwned($this->connection, $userId, 'owner_id', 'progression_weigh_ins', 'progression_weight_goals', 'progression_custom_measures', 'progression_measurement_days', 'progression_photos');
    }

    /**
     * The photos in the period and their file in the archive (`photos/2026-09-28-face-1a2b3c4d.webp`).
     *
     * @return list<array{day: string, pose: string, width: int|null, height: int|null, addedAt: \DateTimeImmutable|null, mediaId: string, path: string}>
     */
    private function photos(UserId $userId, ExportScope $scope): array
    {
        $photos = [];
        foreach (R::rows($this->connection, 'SELECT id, media_id, day, pose, width, height, added_at FROM progression_photos WHERE owner_id = :user', $userId, $scope, 'day', order: 'day, added_at') as $row) {
            $pose = (string) R::string($row['pose']);
            $photoDay = (string) R::string($row['day']);
            $photos[] = [
                'day' => $photoDay,
                'pose' => $pose,
                'width' => R::int($row['width']),
                'height' => R::int($row['height']),
                'addedAt' => R::time($row['added_at']),
                'mediaId' => (string) R::string($row['media_id']),
                'path' => \sprintf('photos/%s-%s-%s.webp', $photoDay, self::POSES[$pose] ?? 'photo', substr((string) R::string($row['id']), 0, 8)),
            ];
        }

        return $photos;
    }

    /**
     * One row per measure taken.
     *
     * @param list<array<string, mixed>> $days
     * @param array<string, string>      $names
     *
     * @return list<list<string|float|null>>
     */
    private static function measurements(array $days, array $names): array
    {
        $rows = [];
        foreach ($days as $day) {
            foreach (R::json($day['measurements']) as $key => $cm) {
                $rows[] = [R::string($day['day']), $names[(string) $key] ?? (string) $key, R::float($cm)];
            }
        }

        return $rows;
    }
}

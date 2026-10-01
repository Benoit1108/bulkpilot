<?php

declare(strict_types=1);

namespace App\Training\Infrastructure\PersonalData;

use App\Shared\Application\PersonalData\Dataset;
use App\Shared\Application\PersonalData\ExportScope;
use App\Shared\Application\PersonalData\PersonalData;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Infrastructure\Persistence\Doctrine\PersonalRows as R;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Programs and their versions, workouts and every set, own exercises and notes (ADR-0030). The
 * shared exercise library is nobody's and stays.
 */
final readonly class TrainingPersonalData implements PersonalData
{
    public const string DOMAIN = 'training';
    private const array MUSCLES = [
        'chest' => 'Pectoraux', 'lats' => 'Grand dorsal', 'upper_back' => 'Haut du dos', 'traps' => 'Trapèzes', 'lower_back' => 'Lombaires',
        'front_delts' => 'Deltoïdes antérieurs', 'side_delts' => 'Deltoïdes latéraux', 'rear_delts' => 'Deltoïdes postérieurs', 'biceps' => 'Biceps',
        'triceps' => 'Triceps', 'forearms' => 'Avant-bras', 'abs' => 'Abdominaux', 'obliques' => 'Obliques', 'glutes' => 'Fessiers', 'quads' => 'Quadriceps',
        'hamstrings' => 'Ischio-jambiers', 'adductors' => 'Adducteurs', 'calves' => 'Mollets',
    ];
    private const array TYPES = ['compound' => 'Polyarticulaire', 'isolation' => 'Isolation'];
    private const array EQUIPMENT = [
        'barbell' => 'Barre', 'dumbbell' => 'Haltères', 'ez_bar' => 'Barre EZ', 'machine' => 'Machine', 'cable' => 'Poulie', 'smith_machine' => 'Smith machine',
        'kettlebell' => 'Kettlebell', 'band' => 'Élastique', 'bodyweight' => 'Poids du corps', 'other' => 'Autre',
    ];
    private const array MEASUREMENTS = ['weight' => 'Charge et répétitions', 'bodyweight' => 'Poids du corps', 'duration' => 'Durée'];
    private const array SET_KINDS = ['warmup' => 'Échauffement', 'approach' => 'Approche', 'normal' => 'Normale', 'effective' => 'Effective', 'drop' => 'Dégressive'];

    public function __construct(private Connection $connection)
    {
    }

    public function domain(): string
    {
        return self::DOMAIN;
    }

    public function title(): string
    {
        return 'Entraînement';
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
        $programs = R::rows($c, 'SELECT id, name, active, current_version_number, created_at, deleted_at FROM training_programs WHERE owner_id = :user', $userId, order: 'created_at');
        $versions = R::rows($c, 'SELECT v.program_id, v.number, v.days, v.created_at FROM training_program_versions v JOIN training_programs p ON p.id = v.program_id WHERE p.owner_id = :user', $userId, order: 'v.created_at');
        $workouts = R::rows($c, 'SELECT started_at, finished_at, program_day, exercises, sets, deleted_at FROM workouts WHERE owner_id = :user', $userId, $scope, 'started_at', moment: true, order: 'started_at');
        $exercises = R::rows($c, 'SELECT name, primary_muscle, type, equipment, measurement, description, created_at, deleted_at FROM exercises WHERE owner_id = :user', $userId, order: 'name');
        $notes = R::rows($c, 'SELECT exercise_id, text, updated_at FROM exercise_notes WHERE owner_id = :user', $userId, order: 'updated_at');
        $programNames = [];
        foreach ($programs as $program) {
            $programNames[(string) R::string($program['id'])] = (string) R::string($program['name']);
        }

        return [
            new Dataset('programmes', 'Programmes', ['Nom', 'Actif', 'Version', 'Créé le', 'Supprimé le'], array_map(static fn (array $row): array => [
                R::string($row['name']), R::bool($row['active']), R::int($row['current_version_number']), R::time($row['created_at']), R::time($row['deleted_at']),
            ], $programs)),
            new Dataset('versions', 'Versions des programmes', ['Programme', 'Version', 'Jours', 'Créée le'], array_map(static fn (array $row): array => [
                $programNames[(string) R::string($row['program_id'])] ?? null, R::int($row['number']), self::dayNames(R::json($row['days'])), R::time($row['created_at']),
            ], $versions)),
            new Dataset('seances', 'Séances', ['Début', 'Fin', 'Jour du programme', 'Exercices', 'Séries', 'Tonnage (kg)', 'Abandonnée le'], array_map(static function (array $row): array {
                $sets = R::json($row['sets']);
                $programDay = R::json($row['program_day']);

                return [
                    R::time($row['started_at']), R::time($row['finished_at']), \is_int($programDay['dayIndex'] ?? null) ? $programDay['dayIndex'] + 1 : null,
                    \count(R::json($row['exercises'])), \count($sets), self::tonnage($sets), R::time($row['deleted_at']),
                ];
            }, $workouts)),
            new Dataset('series', 'Séries', ['Séance du', 'Exercice', 'Type', 'Charge (kg)', 'Répétitions', 'Durée (s)', 'RIR', 'Faite à'], self::sets($workouts)),
            new Dataset('exercices_perso', 'Exercices personnels', ['Nom', 'Muscle principal', 'Type', 'Équipement', 'Mesure', 'Description', 'Créé le', 'Supprimé le'], array_map(static fn (array $row): array => [
                R::string($row['name']), R::label($row['primary_muscle'], self::MUSCLES), R::label($row['type'], self::TYPES), R::label($row['equipment'], self::EQUIPMENT), R::label($row['measurement'], self::MEASUREMENTS),
                R::string($row['description']), R::time($row['created_at']), R::time($row['deleted_at']),
            ], $exercises)),
            new Dataset('notes', 'Notes d’exercice', ['Exercice', 'Note', 'Modifiée le'], $this->notes($notes)),
        ];
    }

    public function files(UserId $userId, ExportScope $scope): array
    {
        return [];
    }

    public function erase(UserId $userId): void
    {
        $this->connection->executeStatement('DELETE FROM training_program_versions WHERE program_id IN (SELECT id FROM training_programs WHERE owner_id = :user)', ['user' => $userId->value]);
        R::deleteOwned($this->connection, $userId, 'owner_id', 'training_programs', 'workouts', 'exercise_notes', 'exercises');
    }

    /**
     * @param array<mixed> $days
     */
    private static function dayNames(array $days): string
    {
        return implode(', ', array_map(static fn (mixed $day): string => \is_array($day) && \is_string($day['name'] ?? null) ? $day['name'] : '?', $days));
    }

    /**
     * @param array<mixed> $sets
     */
    private static function tonnage(array $sets): float
    {
        $total = 0.0;
        foreach ($sets as $set) {
            if (\is_array($set) && !\in_array($set['kind'] ?? null, ['warmup', 'approach'], true)) {
                $total += (R::float($set['loadKg'] ?? null) ?? 0.0) * (R::int($set['reps'] ?? null) ?? 0);
            }
        }

        return round($total, 1);
    }

    /**
     * @param list<array<string, mixed>> $workouts
     *
     * @return list<list<string|int|float|\DateTimeImmutable|null>>
     */
    private static function sets(array $workouts): array
    {
        $rows = [];
        foreach ($workouts as $workout) {
            $exercises = R::json($workout['exercises']);
            foreach (R::json($workout['sets']) as $set) {
                if (!\is_array($set)) {
                    continue;
                }
                $exercise = $exercises[R::int($set['exerciseIndex'] ?? null) ?? -1] ?? null;
                $rows[] = [
                    R::time($workout['started_at']), \is_array($exercise) ? R::string($exercise['name'] ?? null) : null, R::label($set['kind'] ?? null, self::SET_KINDS),
                    R::float($set['loadKg'] ?? null), R::int($set['reps'] ?? null), R::int($set['seconds'] ?? null), R::int($set['rir'] ?? null), R::time($set['doneAt'] ?? null),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $notes
     *
     * @return list<list<string|\DateTimeImmutable|null>>
     */
    private function notes(array $notes): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn (array $note): ?string => R::string($note['exercise_id']), $notes), \is_string(...))));
        $names = [];
        if ([] !== $ids) {
            foreach ($this->connection->fetchAllAssociative('SELECT id, name FROM exercises WHERE id IN (:ids)', ['ids' => $ids], ['ids' => ArrayParameterType::STRING]) as $exercise) {
                $names[(string) R::string($exercise['id'])] = R::string($exercise['name']);
            }
        }

        return array_map(static fn (array $note): array => [$names[(string) R::string($note['exercise_id'])] ?? null, R::string($note['text']), R::time($note['updated_at'])], $notes);
    }
}

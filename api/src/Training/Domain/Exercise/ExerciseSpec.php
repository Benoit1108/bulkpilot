<?php

declare(strict_types=1);

namespace App\Training\Domain\Exercise;

use App\Training\Domain\Exception\InvalidExercise;

/**
 * What describes an exercise, whoever wrote it (the library or a user).
 */
final readonly class ExerciseSpec
{
    public const int MAX_NAME_LENGTH = 100;
    public const int MAX_DESCRIPTION_LENGTH = 2000;
    public const int MAX_MEDIA_URL_LENGTH = 500;
    public const int MAX_SECONDARY_MUSCLES = 6;

    public string $name;
    /** @var list<Muscle> */
    public array $secondaryMuscles;
    public ?string $description;
    public ?string $mediaUrl;

    /**
     * @param list<Muscle> $secondaryMuscles
     * @param string|null  $mediaUrl         an https link to a video or picture of the movement
     */
    public function __construct(
        string $name,
        public Muscle $primaryMuscle,
        array $secondaryMuscles,
        public ExerciseType $type,
        public Equipment $equipment,
        public Measurement $measurement,
        public ExerciseDefaults $defaults,
        ?string $description = null,
        ?string $mediaUrl = null,
    ) {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        if ('' === $name || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw InvalidExercise::because(\sprintf('an exercise name has 1 to %d characters', self::MAX_NAME_LENGTH));
        }
        $secondary = [];
        foreach ($secondaryMuscles as $muscle) {
            if ($muscle !== $primaryMuscle) {
                $secondary[$muscle->value] = $muscle;
            }
        }
        if (\count($secondary) > self::MAX_SECONDARY_MUSCLES) {
            throw InvalidExercise::because(\sprintf('an exercise works at most %d secondary muscles', self::MAX_SECONDARY_MUSCLES));
        }
        $description = null === $description ? '' : trim($description);
        if (mb_strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            throw InvalidExercise::because(\sprintf('a description has at most %d characters', self::MAX_DESCRIPTION_LENGTH));
        }

        $this->name = $name;
        $this->secondaryMuscles = array_values($secondary);
        $this->description = '' === $description ? null : $description;
        $this->mediaUrl = self::validMediaUrl($mediaUrl);
    }

    private static function validMediaUrl(?string $url): ?string
    {
        $url = null === $url ? '' : trim($url);
        if ('' === $url) {
            return null;
        }
        // Only https: the link is opened from the app, never embedded.
        if (mb_strlen($url) > self::MAX_MEDIA_URL_LENGTH || false === filter_var($url, \FILTER_VALIDATE_URL) || 'https' !== parse_url($url, \PHP_URL_SCHEME)) {
            throw InvalidExercise::because('a media link is an https URL of at most 500 characters');
        }

        return $url;
    }
}

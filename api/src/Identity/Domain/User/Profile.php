<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

use App\Identity\Domain\Exception\InvalidProfile;

/**
 * Who the user is. Body metrics that change over time (weight) belong to Progression;
 * activity and goals to Nutrition.
 */
final readonly class Profile
{
    public const int MIN_AGE = 13;
    public const int MAX_AGE = 120;
    public const int MIN_HEIGHT_CM = 100;
    public const int MAX_HEIGHT_CM = 250;

    public string $firstName;

    /**
     * @param \DateTimeImmutable $today reference day for the age rule (not stored)
     */
    public function __construct(
        string $firstName,
        \DateTimeImmutable $today,
        public ?\DateTimeImmutable $birthDate = null,
        public ?Sex $sex = null,
        public ?int $heightCm = null,
    ) {
        $firstName = trim($firstName);
        if ('' === $firstName || mb_strlen($firstName) > 50) {
            throw InvalidProfile::because('first_name', 'First name must contain 1 to 50 characters.');
        }
        $this->firstName = $firstName;

        if (null !== $heightCm && ($heightCm < self::MIN_HEIGHT_CM || $heightCm > self::MAX_HEIGHT_CM)) {
            throw InvalidProfile::because('height', \sprintf('Height must be between %d and %d cm.', self::MIN_HEIGHT_CM, self::MAX_HEIGHT_CM));
        }

        if (null !== $birthDate) {
            $age = $birthDate->diff($today)->y;
            if ($birthDate > $today || $age < self::MIN_AGE || $age > self::MAX_AGE) {
                throw InvalidProfile::because('birth_date', \sprintf('Age must be between %d and %d years.', self::MIN_AGE, self::MAX_AGE));
            }
        }
    }

    public function ageOn(\DateTimeImmutable $day): ?int
    {
        return $this->birthDate?->diff($day)->y;
    }
}

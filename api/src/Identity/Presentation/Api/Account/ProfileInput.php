<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Account;

use App\Identity\Domain\User\Profile;
use Symfony\Component\Validator\Constraints as Assert;

final class ProfileInput
{
    public function parsedBirthDate(): ?\DateTimeImmutable
    {
        if (null === $this->birthDate || '' === $this->birthDate) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->birthDate);

        return false === $date ? null : $date;
    }

    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 50, normalizer: 'trim')]
    public string $firstName = '';

    /** YYYY-MM-DD; kept as a string so that impossible dates (2000-13-45) are rejected, not rolled over. */
    #[Assert\Date]
    public ?string $birthDate = null;

    #[Assert\Choice(choices: ['female', 'male'])]
    public ?string $sex = null;

    #[Assert\Range(min: Profile::MIN_HEIGHT_CM, max: Profile::MAX_HEIGHT_CM)]
    public ?int $heightCm = null;
}

<?php

declare(strict_types=1);

namespace App\Coach\Infrastructure\Persistence\Doctrine;

use App\Coach\Domain\Settings\CoachSettings;
use App\Coach\Domain\Settings\CoachSettingsRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCoachSettingsRepository implements CoachSettingsRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(CoachSettings $settings): void
    {
        $this->entityManager->persist($settings);
    }

    public function ofOrDefault(UserId $userId, \DateTimeImmutable $now): CoachSettings
    {
        return $this->entityManager->find(CoachSettings::class, $userId) ?? CoachSettings::defaults($userId, $now);
    }
}

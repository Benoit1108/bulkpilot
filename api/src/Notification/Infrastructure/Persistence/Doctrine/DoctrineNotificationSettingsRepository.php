<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Persistence\Doctrine;

use App\Notification\Domain\Settings\NotificationSettings;
use App\Notification\Domain\Settings\NotificationSettingsRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineNotificationSettingsRepository implements NotificationSettingsRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(NotificationSettings $settings): void
    {
        $this->entityManager->persist($settings);
    }

    public function ofOrDefault(UserId $userId, \DateTimeImmutable $now): NotificationSettings
    {
        return $this->entityManager->find(NotificationSettings::class, $userId) ?? NotificationSettings::defaults($userId, $now);
    }
}

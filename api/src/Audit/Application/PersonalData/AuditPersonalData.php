<?php

declare(strict_types=1);

namespace App\Audit\Application\PersonalData;

use App\Audit\Application\Activity\ActivityWording;
use App\Audit\Application\Port\Pseudonyms;
use App\Audit\Domain\AuditEvent;
use App\Audit\Domain\AuditEventRepository;
use App\Shared\Application\PersonalData\Dataset;
use App\Shared\Application\PersonalData\ExportScope;
use App\Shared\Application\PersonalData\PersonalData;
use App\Shared\Domain\Identifier\UserId;

/**
 * The account's activity in an export; on erasure, the events stay (what happened, when) but
 * only under a pseudonym and without their details (ADR-0030).
 */
final readonly class AuditPersonalData implements PersonalData
{
    public const string DOMAIN = 'activity';

    public function __construct(private AuditEventRepository $events, private Pseudonyms $pseudonyms)
    {
    }

    public function domain(): string
    {
        return self::DOMAIN;
    }

    public function title(): string
    {
        return 'Activité du compte';
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
        $events = array_values(array_filter($this->events->allOf($userId), static fn (AuditEvent $event): bool => $scope->includes($event->createdAt())));

        return [new Dataset(
            'activite',
            'Activité du compte',
            ['Date', 'Action', 'Par', 'Détails'],
            array_map(static fn (AuditEvent $event): array => [
                $event->createdAt(),
                ActivityWording::label($event->action()),
                ActivityWording::source($event->source()),
                ActivityWording::details($event->action(), $event->details()),
            ], $events),
        )];
    }

    public function files(UserId $userId, ExportScope $scope): array
    {
        return [];
    }

    public function erase(UserId $userId): void
    {
        $this->events->pseudonymise($userId, $this->pseudonyms->of($userId));
    }
}

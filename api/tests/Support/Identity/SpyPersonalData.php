<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Shared\Application\PersonalData\ExportScope;
use App\Shared\Application\PersonalData\PersonalData;
use App\Shared\Domain\Identifier\UserId;

/**
 * A context holding personal data: remembers, in a shared log, in which order it was erased.
 */
final readonly class SpyPersonalData implements PersonalData
{
    /**
     * @param \ArrayObject<int, string> $erased "domain:user id", in order
     */
    public function __construct(private string $domain, private \ArrayObject $erased, private bool $last = false, private bool $failing = false)
    {
    }

    public function domain(): string
    {
        return $this->domain;
    }

    public function title(): string
    {
        return $this->domain;
    }

    public function periodFiltered(): bool
    {
        return true;
    }

    public function erasedLast(): bool
    {
        return $this->last;
    }

    public function export(UserId $userId, ExportScope $scope): array
    {
        return [];
    }

    public function files(UserId $userId, ExportScope $scope): array
    {
        return [];
    }

    public function erase(UserId $userId): void
    {
        if ($this->failing) {
            throw new \RuntimeException($this->domain.' cannot be erased');
        }
        $this->erased[] = $this->domain.':'.$userId->value;
    }
}

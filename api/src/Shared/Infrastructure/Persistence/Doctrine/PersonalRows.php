<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use App\Shared\Application\PersonalData\ExportScope;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\DBAL\Connection;

/**
 * Helpers of the personal-data adapters (ADR-0030), which read and erase whole tables with SQL:
 * the owner's rows (within the scope's period, taken in the user's time zone), values typed as the
 * export writes them, and the erasure of whole tables.
 */
final class PersonalRows
{
    /**
     * The owner's rows: `$sql` selects from a table `WHERE owner_id = :user` (or `user_id`); the
     * scope's period is added on `$column`, a day or a moment (`$moment`: taken in the user's zone).
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(Connection $connection, string $sql, UserId $userId, ?ExportScope $scope = null, ?string $column = null, bool $moment = false, string $order = ''): array
    {
        [$condition, $parameters] = null === $scope || null === $column ? ['', []] : self::period($scope, $column, $moment);

        return $connection->fetchAllAssociative($sql.$condition.('' === $order ? '' : ' ORDER BY '.$order), ['user' => $userId->value, ...$parameters]);
    }

    /**
     * Deletes the owner's rows of each table (`owner_id` unless another column is given).
     */
    public static function deleteOwned(Connection $connection, UserId $userId, string $column, string ...$tables): void
    {
        foreach ($tables as $table) {
            $connection->executeStatement(\sprintf('DELETE FROM %s WHERE %s = :user', $table, $column), ['user' => $userId->value]);
        }
    }

    /** A moment: the archive writes it in the user's time zone. */
    public static function time(mixed $value): ?\DateTimeImmutable
    {
        return \is_string($value) && '' !== $value ? new \DateTimeImmutable($value)->setTimezone(new \DateTimeZone('UTC')) : null;
    }

    public static function string(mixed $value): ?string
    {
        return \is_scalar($value) ? (string) $value : null;
    }

    /**
     * A code shown as the words the user reads (« Manuel » for `manual`); an unknown one as it is.
     *
     * @param array<string, string> $labels
     */
    public static function label(mixed $value, array $labels): ?string
    {
        $code = self::string($value);

        return null === $code ? null : ($labels[$code] ?? $code);
    }

    public static function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    public static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    public static function bool(mixed $value): ?bool
    {
        return null === $value ? null : (bool) $value;
    }

    /** « 72,5 » inside a text (a list of values in one cell); cells keep numbers as numbers. */
    public static function decimal(mixed $value): string
    {
        $number = self::float($value);

        return null === $number ? '' : str_replace('.', ',', (string) $number);
    }

    /**
     * @return array<mixed>
     */
    public static function json(mixed $value): array
    {
        if (!\is_string($value) || '' === $value) {
            return [];
        }
        $decoded = json_decode($value, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * The scope's period on a day column, or on a moment taken as the user's day: an SQL condition
     * to AND, and its parameters.
     *
     * @return array{string, array<string, string>}
     */
    public static function period(ExportScope $scope, string $column, bool $moment = false): array
    {
        $day = $moment ? \sprintf('CAST(%s AT TIME ZONE :zone AS DATE)', $column) : $column;
        $conditions = [];
        $parameters = [];
        if (null !== $scope->from) {
            $conditions[] = \sprintf('%s >= :from', $day);
            $parameters['from'] = $scope->from->format('Y-m-d');
        }
        if (null !== $scope->to) {
            $conditions[] = \sprintf('%s <= :to', $day);
            $parameters['to'] = $scope->to->format('Y-m-d');
        }
        if ($moment && [] !== $conditions) {
            $parameters['zone'] = $scope->timeZone;
        }

        return [[] === $conditions ? '' : ' AND '.implode(' AND ', $conditions), $parameters];
    }
}

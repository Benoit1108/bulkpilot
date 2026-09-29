<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Api;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The "next page" parameters of the lists, parsed strictly: an unreadable one is refused rather
 * than ignored (a client paging on it would get the first page again and again).
 */
final class Cursors
{
    /** An ISO 8601 date-time with its offset, as the API writes them (`2026-09-30T08:00:00+00:00`). */
    private const string TIME = '/^(\d{4})-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?(Z|[+-]\d{2}:\d{2})$/';
    private const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
    /** Beyond these years, no record can be (and the database would refuse the value). */
    private const int FIRST_YEAR = 2000;
    private const int LAST_YEAR = 2100;

    public static function time(?string $value, string $name): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (1 !== preg_match(self::TIME, $value, $parts) || (int) $parts[1] < self::FIRST_YEAR || (int) $parts[1] > self::LAST_YEAR) {
            throw new BadRequestHttpException(\sprintf('"%s" is an ISO 8601 date-time with its offset.', $name));
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new BadRequestHttpException(\sprintf('"%s" is an ISO 8601 date-time with its offset.', $name));
        }
    }

    public static function day(?string $value, string $name): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return CalendarDays::parse($value) ?? throw new BadRequestHttpException(\sprintf('"%s" is a YYYY-MM-DD date.', $name));
    }

    public static function id(?string $value, string $name): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (1 !== preg_match(self::UUID, $value)) {
            throw new BadRequestHttpException(\sprintf('"%s" is an id.', $name));
        }

        return $value;
    }
}

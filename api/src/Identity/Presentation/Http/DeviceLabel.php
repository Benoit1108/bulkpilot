<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http;

/**
 * Coarse, human-readable device name ("Firefox · Android") for the list of signed-in devices.
 * The raw User-Agent is not stored.
 */
final class DeviceLabel
{
    private const array BROWSERS = ['Edg/' => 'Edge', 'OPR/' => 'Opera', 'Firefox/' => 'Firefox', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari'];
    private const array SYSTEMS = ['Android' => 'Android', 'iPhone' => 'iOS', 'iPad' => 'iPadOS', 'Windows' => 'Windows', 'Mac OS X' => 'macOS', 'CrOS' => 'ChromeOS', 'Linux' => 'Linux'];

    public static function fromUserAgent(?string $userAgent): string
    {
        if (null === $userAgent || '' === $userAgent) {
            return 'Appareil inconnu';
        }

        $browser = self::firstMatch(self::BROWSERS, $userAgent);
        $system = self::firstMatch(self::SYSTEMS, $userAgent);

        $parts = array_filter([$browser, $system], static fn (?string $part): bool => null !== $part);

        return [] === $parts ? 'Appareil inconnu' : implode(' · ', $parts);
    }

    /**
     * @param array<string, string> $candidates
     */
    private static function firstMatch(array $candidates, string $userAgent): ?string
    {
        foreach ($candidates as $needle => $label) {
            if (str_contains($userAgent, $needle)) {
                return $label;
            }
        }

        return null;
    }
}

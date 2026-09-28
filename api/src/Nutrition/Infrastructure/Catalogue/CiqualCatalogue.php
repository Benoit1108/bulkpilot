<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\Catalogue;

use App\Nutrition\Application\Catalogue\CatalogueEntry;
use App\Nutrition\Application\Port\FoodCatalogue;
use App\Nutrition\Domain\Food\FoodSource;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The ANSES CIQUAL composition table (Licence Ouverte Etalab 2.0), in its XML distribution:
 * alim_*.xml (foods), alim_grp_*.xml (groups), compo_*.xml (one value per food and constituent).
 * Files are windows-1252 and not well-formed XML; values use a decimal comma, "-" when unknown, "traces" and "< x" when
 * below the quantification limit (read as 0).
 */
final readonly class CiqualCatalogue implements FoodCatalogue
{
    public const string OFFICIAL_ARCHIVE = 'https://ciqual.anses.fr/cms/sites/default/files/inline-files/XML_2020_07_07.zip';
    /** SHA-256 of the 2020 archive: the catalogue is shared by every user, it must be the published one. */
    public const string OFFICIAL_ARCHIVE_SHA256 = 'cab13941ca693b7007c2f0fed2288fd5b94073fe6d598c9773297cf37aa59ca8';
    /** The archive is ~3.5 MB and its three useful files ~60 MB: anything much bigger is not CIQUAL. */
    public const int MAX_ARCHIVE_BYTES = 20_000_000;
    public const int MAX_EXTRACTED_BYTES = 200_000_000;

    /** CIQUAL constituent code => the value it gives (CatalogueEntry field, or a fallback for one). */
    private const array CONSTITUENTS = [
        '328' => 'energyKcal', // Energie, Règlement UE N° 1169/2011 (kcal/100 g)
        '333' => 'energyKcalJones', // Energie, N x facteur Jones, avec fibres (kcal/100 g): fallback
        '25000' => 'protein', // Protéines, N x facteur de Jones
        '31000' => 'carbohydrate', // Glucides
        '40000' => 'fat', // Lipides
        '34100' => 'fibre', // Fibres alimentaires
        '32000' => 'sugars', // Sucres
        '40302' => 'saturatedFat', // AG saturés
        '10004' => 'salt', // Sel chlorure de sodium
    ];

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function source(): FoodSource
    {
        return FoodSource::Ciqual;
    }

    /**
     * Without a location, the official archive is downloaded and checked against its known digest.
     * Another https:// URL is downloaded as given (a newer release, before updating the digest).
     */
    public function read(?string $location): iterable
    {
        $expectedDigest = null === $location ? self::OFFICIAL_ARCHIVE_SHA256 : null;
        $location ??= self::OFFICIAL_ARCHIVE;
        if (!str_starts_with($location, 'https://')) {
            yield from $this->entries($location);

            return;
        }

        $directory = self::temporaryDirectory();
        try {
            $this->download($location, $directory, $expectedDigest);
            yield from $this->entries($directory);
        } finally {
            array_map(unlink(...), self::glob($directory.'/*'));
            rmdir($directory);
        }
    }

    /**
     * @return \Generator<CatalogueEntry>
     */
    private function entries(string $directory): \Generator
    {
        $categories = $this->categories(self::file($directory, 'alim_grp_'));
        $values = $this->values(self::file($directory, 'compo_'));

        foreach (self::records(self::file($directory, 'alim_2'), 'ALIM') as $food) {
            $code = $food['alim_code'] ?? '';
            $name = $food['alim_nom_fr'] ?? '';
            if ('' === $code || '' === $name) {
                continue;
            }
            $of = $values[$code] ?? [];

            yield new CatalogueEntry(
                $code,
                $name,
                $categories[$food['alim_ssgrp_code'] ?? ''] ?? $categories[$food['alim_grp_code'] ?? ''] ?? null,
                $of['energyKcal'] ?? $of['energyKcalJones'] ?? null,
                $of['protein'] ?? null,
                $of['carbohydrate'] ?? null,
                $of['fat'] ?? null,
                $of['fibre'] ?? null,
                $of['sugars'] ?? null,
                $of['saturatedFat'] ?? null,
                $of['salt'] ?? null,
            );
        }
    }

    /**
     * Parses one CIQUAL value: "12,5" → 12.5, "traces" / "< 0,5" → 0, "-" → unknown.
     */
    public static function parseValue(string $raw): ?float
    {
        $raw = trim($raw);
        if ('' === $raw || '-' === $raw) {
            return null;
        }
        if ('traces' === mb_strtolower($raw) || str_starts_with($raw, '<')) {
            return 0.0;
        }
        $number = str_replace(',', '.', $raw);

        return is_numeric($number) ? max(0.0, (float) $number) : null;
    }

    /**
     * @return array<string, string> sub-group and group code => French name
     */
    private function categories(string $file): array
    {
        $names = [];
        foreach (self::records($file, 'ALIM_GRP') as $group) {
            foreach (['alim_grp' => 'alim_grp_code', 'alim_ssgrp' => 'alim_ssgrp_code'] as $prefix => $codeField) {
                $name = $group[$prefix.'_nom_fr'] ?? '-';
                if ('-' !== $name && '' !== ($group[$codeField] ?? '')) {
                    $names[$group[$codeField]] = mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1);
                }
            }
        }

        return $names;
    }

    /**
     * @return array<string, array<string, float|null>> food code => field => value
     */
    private function values(string $file): array
    {
        $values = [];
        foreach (self::records($file, 'COMPO') as $row) {
            $field = self::CONSTITUENTS[$row['const_code'] ?? ''] ?? null;
            if (null !== $field) {
                $values[$row['alim_code'] ?? ''][$field] = self::parseValue($row['teneur'] ?? '-');
            }
        }

        return $values;
    }

    /**
     * Streams the flat records of a CIQUAL file (the compositions file is ~60 MB).
     *
     * Read line by line rather than with an XML parser: the published files are not well-formed
     * (values such as "< 2,2" are not escaped), but each field sits on its own line.
     *
     * @return \Generator<array<string, string>>
     */
    private static function records(string $file, string $element): \Generator
    {
        $handle = fopen($file, 'r');
        if (false === $handle) {
            throw new \RuntimeException(\sprintf('Cannot open "%s".', $file));
        }

        try {
            $record = null;
            while (false !== ($line = fgets($handle))) {
                $line = trim($line);
                if ('<'.$element.'>' === $line) {
                    $record = [];
                } elseif ('</'.$element.'>' === $line) {
                    if (null !== $record) {
                        yield $record;
                    }
                    $record = null;
                } elseif (null !== $record && 1 === preg_match('#^<([A-Za-z_]+)>(.*)</\1>$#', $line, $field)) {
                    $record[$field[1]] = trim(mb_convert_encoding($field[2], 'UTF-8', 'Windows-1252'));
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private static function file(string $directory, string $prefix): string
    {
        $matches = self::glob(rtrim($directory, '/').'/'.$prefix.'*.xml');
        if (1 !== \count($matches)) {
            throw new \RuntimeException(\sprintf('Expected one "%s*.xml" file in "%s", found %d.', $prefix, $directory, \count($matches)));
        }

        return $matches[0];
    }

    /**
     * @return list<string>
     */
    private static function glob(string $pattern): array
    {
        $matches = glob($pattern);

        return false === $matches ? [] : $matches;
    }

    private static function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().'/ciqual-'.bin2hex(random_bytes(6));
        if (!mkdir($directory, 0o700) && !is_dir($directory)) {
            throw new \RuntimeException(\sprintf('Cannot create "%s".', $directory));
        }

        return $directory;
    }

    /**
     * Over https only (no redirect, so no downgrade), bounded in size and time; then extracts the
     * three flat XML files the reader needs, nothing else (no zip-slip, no zip bomb).
     */
    private function download(string $url, string $directory, ?string $expectedDigest): void
    {
        $archive = $directory.'/ciqual.zip';
        $handle = fopen($archive, 'w');
        if (false === $handle) {
            throw new \RuntimeException(\sprintf('Cannot write "%s".', $archive));
        }
        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 30, 'max_duration' => 300, 'max_redirects' => 0]);
            if (200 !== $response->getStatusCode()) {
                throw new \RuntimeException(\sprintf('The CIQUAL download answered HTTP %d.', $response->getStatusCode()));
            }
            $bytes = 0;
            foreach ($this->httpClient->stream($response) as $chunk) {
                $content = $chunk->getContent();
                $bytes += \strlen($content);
                if ($bytes > self::MAX_ARCHIVE_BYTES) {
                    $response->cancel();

                    throw new \RuntimeException('The CIQUAL archive is larger than expected.');
                }
                fwrite($handle, $content);
            }
        } finally {
            fclose($handle);
        }

        if (null !== $expectedDigest && !hash_equals($expectedDigest, (string) hash_file('sha256', $archive))) {
            throw new \RuntimeException('The downloaded CIQUAL archive is not the published one (SHA-256 mismatch).');
        }

        $zip = new \ZipArchive();
        if (true !== $zip->open($archive)) {
            throw new \RuntimeException('The downloaded CIQUAL archive is not a valid ZIP file.');
        }
        try {
            $extracted = 0;
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $name = (string) $zip->getNameIndex($i);
                if (1 !== preg_match('/^(alim|alim_grp|compo)_[0-9_]+\.xml$/', $name)) {
                    continue;
                }
                $stat = $zip->statIndex($i);
                $extracted += false === $stat ? 0 : $stat['size'];
                if ($extracted > self::MAX_EXTRACTED_BYTES) {
                    throw new \RuntimeException('The CIQUAL archive expands to more than expected.');
                }
                $zip->extractTo($directory, $name);
            }
        } finally {
            $zip->close();
        }
    }
}

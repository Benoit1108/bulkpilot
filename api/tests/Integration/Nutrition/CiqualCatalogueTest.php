<?php

declare(strict_types=1);

namespace App\Tests\Integration\Nutrition;

use App\Nutrition\Application\Catalogue\CatalogueEntry;
use App\Nutrition\Domain\Food\FoodSource;
use App\Nutrition\Infrastructure\Catalogue\CiqualCatalogue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Against an excerpt of the real CIQUAL 2020 files (windows-1252, CRLF, unescaped "< x" values).
 */
final class CiqualCatalogueTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../Fixtures/ciqual';

    public function testTheFoodsAreReadWithTheirCompositionAndCategory(): void
    {
        $entries = $this->entries(new CiqualCatalogue(new MockHttpClient())->read(self::FIXTURES));

        self::assertCount(12, $entries);
        $chicken = self::entry($entries, '36017');
        self::assertSame('Poulet, filet, sans peau, cru', $chicken->name);
        self::assertSame('Viandes crues', $chicken->category);
        self::assertSame(110.0, $chicken->energyKcal);
        self::assertSame(23.4, $chicken->protein);
        self::assertSame(1.5, $chicken->fat);
        self::assertSame('Oeuf, cru', self::entry($entries, '22000')->name);
        self::assertSame(901.0, self::entry($entries, '17440')->energyKcal);
        self::assertNull(self::entry($entries, '31067')->energyKcal, 'molasses has no energy value');
    }

    public function testItIsTheCiqualSource(): void
    {
        self::assertSame(FoodSource::Ciqual, new CiqualCatalogue(new MockHttpClient())->source());
    }

    #[TestWith(['12,5', 12.5])]
    #[TestWith([' 3 ', 3.0])]
    #[TestWith(['traces', 0.0])]
    #[TestWith(['< 0,5', 0.0])]
    #[TestWith(['-', null])]
    #[TestWith(['', null])]
    #[TestWith(['n/a', null])]
    public function testValuesAreParsedTheFrenchWay(string $raw, ?float $value): void
    {
        self::assertSame($value, CiqualCatalogue::parseValue($raw));
    }

    public function testAnArchiveIsDownloadedOverHttpsExtractedAndCleanedUp(): void
    {
        $requested = [];
        $client = self::serving(self::archive(), $requested);
        $before = self::downloads();

        $entries = $this->entries(new CiqualCatalogue($client)->read('https://ciqual.example/archive.zip'));

        self::assertCount(12, $entries);
        self::assertSame(['GET https://ciqual.example/archive.zip'], $requested);
        self::assertSame($before, self::downloads(), 'the download is removed');
        self::assertFileDoesNotExist(sys_get_temp_dir().'/escape.xml', 'nothing outside the flat XML files is extracted');
    }

    public function testTheOfficialArchiveMustBeThePublishedOne(): void
    {
        $requested = [];
        $client = self::serving(self::archive(), $requested);
        $before = self::downloads();

        try {
            iterator_to_array(new CiqualCatalogue($client)->read(null));
            self::fail('a tampered archive is refused');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('SHA-256', $e->getMessage());
        }
        self::assertSame(['GET '.CiqualCatalogue::OFFICIAL_ARCHIVE], $requested);
        self::assertSame($before, self::downloads());
    }

    /**
     * @return iterable<string, array{MockResponse, string}>
     */
    public static function failedDownloads(): iterable
    {
        yield 'server error' => [new MockResponse('', ['http_code' => 500]), 'HTTP 500'];
        yield 'not a zip' => [new MockResponse('not a zip'), 'not a valid ZIP'];
        yield 'too big' => [new MockResponse(str_repeat('x', CiqualCatalogue::MAX_ARCHIVE_BYTES + 1)), 'larger than expected'];
    }

    #[DataProvider('failedDownloads')]
    public function testAFailedDownloadLeavesNothingBehind(MockResponse $response, string $message): void
    {
        $before = self::downloads();

        try {
            iterator_to_array(new CiqualCatalogue(new MockHttpClient($response))->read('https://ciqual.example/archive.zip'));
            self::fail('the download fails');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString($message, $e->getMessage());
        }

        self::assertSame($before, self::downloads());
    }

    /**
     * @param list<string> $requested
     */
    private static function serving(string $body, array &$requested): MockHttpClient
    {
        return new MockHttpClient(static function (string $method, string $url) use ($body, &$requested): MockResponse {
            $requested[] = $method.' '.$url;

            return new MockResponse($body);
        });
    }

    /**
     * The three useful files of the excerpt, plus an entry trying to escape the directory.
     */
    private static function archive(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ciqual');
        \assert(\is_string($path));
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        foreach (['alim_2020_07_07.xml', 'alim_grp_2020_07_07.xml', 'compo_2020_07_07.xml'] as $file) {
            $zip->addFile(self::FIXTURES.'/'.$file, $file);
        }
        $zip->addFromString('../escape.xml', 'zip-slip');
        $zip->close();
        $body = (string) file_get_contents($path);
        unlink($path);

        return $body;
    }

    public function testAMissingFileIsReported(): void
    {
        $this->expectExceptionMessage('Expected one "alim_grp_*.xml" file');

        iterator_to_array(new CiqualCatalogue(new MockHttpClient())->read(sys_get_temp_dir().'/does-not-exist'));
    }

    /**
     * @param array<array-key, CatalogueEntry> $entries
     */
    private static function entry(array $entries, string $reference): CatalogueEntry
    {
        self::assertArrayHasKey($reference, $entries);

        return $entries[$reference];
    }

    /**
     * @return list<string>
     */
    private static function downloads(): array
    {
        $found = glob(sys_get_temp_dir().'/ciqual-*');

        return false === $found ? [] : $found;
    }

    /**
     * @param iterable<CatalogueEntry> $entries
     *
     * @return array<array-key, CatalogueEntry>
     */
    private function entries(iterable $entries): array
    {
        $byReference = [];
        foreach ($entries as $entry) {
            $byReference[$entry->reference] = $entry;
        }

        return $byReference;
    }
}

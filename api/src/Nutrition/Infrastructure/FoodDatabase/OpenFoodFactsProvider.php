<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\FoodDatabase;

use App\Nutrition\Application\Barcode\ExternalProduct;
use App\Nutrition\Application\Barcode\ProductDatabaseUnavailable;
use App\Nutrition\Application\Port\FoodDatabaseProvider;
use App\Nutrition\Domain\Food\Barcode;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Open Food Facts (ODbL), read API v2. Only the fields we use are requested; the service asks
 * clients to identify themselves with a User-Agent and to keep product reads under 100 a minute
 * (the scans of one person, cached by CachedFoodDatabaseProvider).
 */
final readonly class OpenFoodFactsProvider implements FoodDatabaseProvider
{
    public const string ENDPOINT = 'https://world.openfoodfacts.org/api/v2/product/%s.json';
    private const string FIELDS = 'code,product_name,product_name_fr,brands,categories,quantity,serving_quantity,serving_quantity_unit,serving_size,nutriments';
    private const float KJ_PER_KCAL = 4.184;

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(OPEN_FOOD_FACTS_USER_AGENT)%')]
        private string $userAgent,
    ) {
    }

    public function findByBarcode(Barcode $barcode): ?ExternalProduct
    {
        try {
            $response = $this->httpClient->request('GET', \sprintf(self::ENDPOINT, $barcode->value), [
                'query' => ['fields' => self::FIELDS, 'lc' => 'fr'],
                'headers' => ['User-Agent' => $this->userAgent, 'Accept' => 'application/json'],
                'timeout' => 5,
                'max_duration' => 8,
                'max_redirects' => 0,
            ]);
            $status = $response->getStatusCode();
            if (404 === $status) {
                return null;
            }
            if (200 !== $status) {
                throw ProductDatabaseUnavailable::because(\sprintf('Open Food Facts answered HTTP %d', $status));
            }
            $body = $response->toArray();
        } catch (ExceptionInterface $e) {
            throw ProductDatabaseUnavailable::because('Open Food Facts cannot be reached', $e);
        }

        $product = $body['product'] ?? null;
        if (1 !== ($body['status'] ?? 0) || !\is_array($product)) {
            return null;
        }

        return self::normalise($barcode, $product);
    }

    /**
     * @param array<mixed> $product
     */
    public static function normalise(Barcode $barcode, array $product): ExternalProduct
    {
        $nutriments = \is_array($product['nutriments'] ?? null) ? $product['nutriments'] : [];
        $number = static fn (string $key): ?float => self::quantity($nutriments[$key] ?? null);

        $energy = $number('energy-kcal_100g');
        if (null === $energy) {
            $kilojoules = $number('energy-kj_100g') ?? $number('energy_100g');
            $energy = null === $kilojoules ? null : round($kilojoules / self::KJ_PER_KCAL, 1);
        }

        $serving = self::quantity($product['serving_quantity'] ?? null);

        return new ExternalProduct(
            $barcode,
            self::text($product['product_name_fr'] ?? null) ?? self::text($product['product_name'] ?? null),
            self::first($product['brands'] ?? null),
            self::last($product['categories'] ?? null),
            $energy,
            $number('proteins_100g'),
            $number('carbohydrates_100g'),
            $number('fat_100g'),
            $number('fiber_100g'),
            $number('sugars_100g'),
            $number('saturated-fat_100g'),
            $number('salt_100g'),
            null !== $serving && $serving > 0 && self::servingInGrams($product) ? $serving : null,
            self::text($product['quantity'] ?? null, 50),
        );
    }

    /**
     * A number as contributors typed it ("12,5" included). Negative or non-finite values are
     * entry mistakes: unknown rather than zero, so that the user is asked for them.
     */
    private static function quantity(mixed $value): ?float
    {
        if (\is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }
        if (!is_numeric($value)) {
            return null;
        }
        $number = (float) $value;

        return is_finite($number) && $number >= 0 ? $number : null;
    }

    /**
     * serving_quantity is in serving_quantity_unit; older products have no unit, then only a
     * serving_size ending in grams ("30 g", "1 bol (30 g)") says it is a weight, not millilitres.
     *
     * @param array<mixed> $product
     */
    private static function servingInGrams(array $product): bool
    {
        $unit = $product['serving_quantity_unit'] ?? null;
        if (\is_string($unit) && '' !== trim($unit)) {
            return 'g' === mb_strtolower(trim($unit));
        }
        $size = $product['serving_size'] ?? null;

        return \is_string($size) && 1 === preg_match('/\d\s*g\s*\)?$/iu', trim($size));
    }

    private static function text(mixed $value, int $max = 200): ?string
    {
        if (!\is_string($value)) {
            return null;
        }
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return '' === $value ? null : mb_substr($value, 0, $max);
    }

    /** "Ferrero, Nutella" → "Ferrero" */
    private static function first(mixed $list): ?string
    {
        return \is_string($list) ? self::text(explode(',', $list)[0], 100) : null;
    }

    /** Categories go from general to specific: the last one says best what the product is. */
    private static function last(mixed $list): ?string
    {
        if (!\is_string($list)) {
            return null;
        }
        $parts = explode(',', $list);

        return self::text(end($parts), 150);
    }
}

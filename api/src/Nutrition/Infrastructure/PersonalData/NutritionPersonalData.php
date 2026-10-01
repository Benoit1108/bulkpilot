<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\PersonalData;

use App\Shared\Application\PersonalData\Dataset;
use App\Shared\Application\PersonalData\ExportScope;
use App\Shared\Application\PersonalData\PersonalData;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Infrastructure\Persistence\Doctrine\PersonalRows as R;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * The journal, targets, own foods, recipes, favourite meals and meal split (ADR-0030). The shared
 * catalogues (CIQUAL, Open Food Facts) are nobody's and stay.
 */
final readonly class NutritionPersonalData implements PersonalData
{
    public const string DOMAIN = 'nutrition';
    private const array SLOTS = ['breakfast' => 'Petit-déjeuner', 'lunch' => 'Déjeuner', 'snack' => 'Collation', 'gainer' => 'Gainer', 'dinner' => 'Dîner'];
    private const array SOURCES = ['estimate' => 'Estimation', 'manual' => 'Manuel', 'coach' => 'Coach'];
    private const array STATES = ['raw' => 'Cru', 'cooked' => 'Cuit', 'not_applicable' => 'Sans objet'];

    public function __construct(private Connection $connection)
    {
    }

    public function domain(): string
    {
        return self::DOMAIN;
    }

    public function title(): string
    {
        return 'Nutrition';
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
        $c = $this->connection;
        $entries = R::rows($c, 'SELECT day, slot, food_name, grams, food_per100g_energy_kcal, food_per100g_protein, food_per100g_carbohydrate, food_per100g_fat, logged_at FROM journal_entries WHERE owner_id = :user', $userId, $scope, 'day', order: 'day, logged_at');
        $foods = R::rows($c, 'SELECT name, brand, category, state, per100g_energy_kcal, per100g_protein, per100g_carbohydrate, per100g_fat, per100g_fibre, barcode, deleted_at FROM foods WHERE owner_id = :user', $userId, order: 'name');
        $recipes = R::rows($c, 'SELECT name, servings, cooked_weight_grams, ingredients, created_at, deleted_at FROM recipes WHERE owner_id = :user', $userId, order: 'name');
        $favorites = R::rows($c, 'SELECT name, items, created_at FROM favorite_meals WHERE owner_id = :user', $userId, order: 'name');
        $split = R::rows($c, 'SELECT shares FROM meal_splits WHERE owner_id = :user', $userId);
        $names = $this->foodNames([...$recipes, ...$favorites]);

        return [
            new Dataset('journal', 'Journal', ['Jour', 'Repas', 'Aliment', 'Quantité (g)', 'Calories (kcal)', 'Protéines (g)', 'Glucides (g)', 'Lipides (g)', 'Saisi le'], array_map(static function (array $row): array {
                $grams = R::float($row['grams']) ?? 0.0;
                $of = static fn (mixed $per100g): ?float => null === R::float($per100g) ? null : round(R::float($per100g) * $grams / 100, 1);

                return [
                    R::string($row['day']), R::label($row['slot'], self::SLOTS), R::string($row['food_name']), $grams,
                    $of($row['food_per100g_energy_kcal']), $of($row['food_per100g_protein']), $of($row['food_per100g_carbohydrate']), $of($row['food_per100g_fat']), R::time($row['logged_at']),
                ];
            }, $entries)),
            new Dataset('objectifs', 'Objectifs', ['À partir du', 'Calories (kcal)', 'Protéines (g)', 'Glucides (g)', 'Lipides (g)', 'Source', 'Fixé le'], array_map(static fn (array $row): array => [
                R::string($row['effective_from']), R::int($row['energy_kcal']), R::int($row['protein_grams']), R::int($row['carbohydrate_grams']), R::int($row['fat_grams']), R::label($row['source'], self::SOURCES), R::time($row['set_at']),
            ], $this->targets($userId, $scope))),
            new Dataset('aliments_perso', 'Aliments personnels', ['Nom', 'Marque', 'Catégorie', 'État', 'Calories (kcal/100 g)', 'Protéines (g/100 g)', 'Glucides (g/100 g)', 'Lipides (g/100 g)', 'Fibres (g/100 g)', 'Code-barres', 'Supprimé le'], array_map(static fn (array $row): array => [
                R::string($row['name']), R::string($row['brand']), R::string($row['category']), R::label($row['state'], self::STATES), R::float($row['per100g_energy_kcal']), R::float($row['per100g_protein']),
                R::float($row['per100g_carbohydrate']), R::float($row['per100g_fat']), R::float($row['per100g_fibre']), R::string($row['barcode']), R::time($row['deleted_at']),
            ], $foods)),
            new Dataset('recettes', 'Recettes', ['Nom', 'Portions', 'Poids cuit (g)', 'Ingrédients', 'Créée le', 'Supprimée le'], array_map(static fn (array $row): array => [
                R::string($row['name']), R::int($row['servings']), R::float($row['cooked_weight_grams']), self::items(R::json($row['ingredients']), $names), R::time($row['created_at']), R::time($row['deleted_at']),
            ], $recipes)),
            new Dataset('repas_favoris', 'Repas favoris', ['Nom', 'Aliments', 'Créé le'], array_map(static fn (array $row): array => [
                R::string($row['name']), self::items(R::json($row['items']), $names), R::time($row['created_at']),
            ], $favorites)),
            new Dataset('repartition', 'Répartition des repas', ['Repas', 'Part (%)'], self::shares($split)),
        ];
    }

    public function files(UserId $userId, ExportScope $scope): array
    {
        return [];
    }

    public function erase(UserId $userId): void
    {
        R::deleteOwned($this->connection, $userId, 'owner_id', 'journal_entries', 'nutrition_targets', 'favorite_meals', 'recipes', 'meal_splits', 'foods');
    }

    /**
     * The targets set in the period, and the one already in force when it starts (every day of
     * the period is judged against a target).
     *
     * @return list<array<string, mixed>>
     */
    private function targets(UserId $userId, ExportScope $scope): array
    {
        $targets = R::rows($this->connection, 'SELECT effective_from, energy_kcal, protein_grams, carbohydrate_grams, fat_grams, source, set_at FROM nutrition_targets WHERE owner_id = :user', $userId, order: 'effective_from, set_at');
        $kept = [];
        $inForce = null;
        foreach ($targets as $target) {
            $day = (string) R::string($target['effective_from']);
            if (null !== $scope->from && $day < $scope->from->format('Y-m-d')) {
                $inForce = $target;
            } elseif (null === $scope->to || $day <= $scope->to->format('Y-m-d')) {
                $kept[] = $target;
            }
        }

        return null === $inForce ? $kept : [$inForce, ...$kept];
    }

    /**
     * The names of the foods the recipes and favourites are made of (any food: from the catalogues too).
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, string>
     */
    private function foodNames(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            foreach (R::json($row['ingredients'] ?? $row['items'] ?? null) as $item) {
                if (\is_array($item) && \is_string($item['foodId'] ?? null)) {
                    $ids[$item['foodId']] = true;
                }
            }
        }
        if ([] === $ids) {
            return [];
        }
        $names = [];
        foreach ($this->connection->fetchAllAssociative('SELECT id, name FROM foods WHERE id IN (:ids)', ['ids' => array_keys($ids)], ['ids' => ArrayParameterType::STRING]) as $food) {
            $names[(string) R::string($food['id'])] = (string) R::string($food['name']);
        }

        return $names;
    }

    /**
     * « Riz (300 g), Poulet (180 g) ».
     *
     * @param array<mixed>          $items
     * @param array<string, string> $names
     */
    private static function items(array $items, array $names): string
    {
        $parts = [];
        foreach ($items as $item) {
            if (\is_array($item) && \is_string($item['foodId'] ?? null)) {
                $parts[] = \sprintf('%s (%s g)', $names[$item['foodId']] ?? 'aliment supprimé', R::decimal($item['grams'] ?? 0));
            }
        }

        return implode(', ', $parts);
    }

    /**
     * @param list<array<string, mixed>> $split
     *
     * @return list<list<string|int|null>>
     */
    private static function shares(array $split): array
    {
        $rows = [];
        foreach (R::json($split[0]['shares'] ?? null) as $slot => $share) {
            $rows[] = [R::label($slot, self::SLOTS), R::int($share)];
        }

        return $rows;
    }
}

<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Recipe;

use App\Nutrition\Domain\Exception\InvalidRecipe;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\UserId;

/**
 * A dish made of foods, split into servings. Its composition follows its foods (see
 * RecipeComposition); journal entries keep the values of the day they were logged.
 *
 * The cooked weight, when given, is what the pot weighs at the end (water lost or absorbed):
 * servings and "per 100 g" are then taken on the cooked dish.
 */
class Recipe extends AggregateRoot
{
    public const int MAX_INGREDIENTS = 50;
    public const int MAX_SERVINGS = 50;
    public const float MAX_COOKED_GRAMS = 20000.0;
    public const int MAX_NAME_LENGTH = 200;

    private int $version = 1;
    private ?\DateTimeImmutable $deletedAt = null;

    /**
     * @param list<Ingredient> $ingredients
     */
    private function __construct(
        private readonly RecipeId $id,
        private readonly UserId $ownerId,
        private string $name,
        private int $servings,
        private ?float $cookedWeightGrams,
        private array $ingredients,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param list<Ingredient> $ingredients
     */
    public static function create(RecipeId $id, UserId $owner, string $name, int $servings, ?float $cookedWeightGrams, array $ingredients, \DateTimeImmutable $now): self
    {
        return new self($id, $owner, self::validName($name), self::validServings($servings), self::validCookedWeight($cookedWeightGrams), self::validIngredients($ingredients), $now);
    }

    /**
     * @param list<Ingredient> $ingredients
     */
    public function edit(string $name, int $servings, ?float $cookedWeightGrams, array $ingredients): void
    {
        $this->name = self::validName($name);
        $this->servings = self::validServings($servings);
        $this->cookedWeightGrams = self::validCookedWeight($cookedWeightGrams);
        $this->ingredients = self::validIngredients($ingredients);
    }

    /**
     * Soft delete: favourites made with it keep working.
     */
    public function delete(\DateTimeImmutable $now): void
    {
        $this->deletedAt ??= $now;
    }

    public function isVisibleTo(UserId $userId): bool
    {
        return null === $this->deletedAt && $this->belongsTo($userId);
    }

    public function belongsTo(UserId $userId): bool
    {
        return $this->ownerId->equals($userId);
    }

    public function id(): RecipeId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function servings(): int
    {
        return $this->servings;
    }

    public function cookedWeightGrams(): ?float
    {
        return $this->cookedWeightGrams;
    }

    /**
     * @return list<Ingredient>
     */
    public function ingredients(): array
    {
        return $this->ingredients;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function validName(string $name): string
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw InvalidRecipe::because('a recipe name has 1 to 200 characters');
        }

        return $name;
    }

    private static function validServings(int $servings): int
    {
        if ($servings < 1 || $servings > self::MAX_SERVINGS) {
            throw InvalidRecipe::because(\sprintf('a recipe makes 1 to %d servings', self::MAX_SERVINGS));
        }

        return $servings;
    }

    private static function validCookedWeight(?float $grams): ?float
    {
        if (null !== $grams && ($grams <= 0 || $grams > self::MAX_COOKED_GRAMS)) {
            throw InvalidRecipe::because(\sprintf('a cooked dish weighs between 0 and %d g', (int) self::MAX_COOKED_GRAMS));
        }

        return null === $grams ? null : round($grams, 1);
    }

    /**
     * @param list<Ingredient> $ingredients
     *
     * @return list<Ingredient>
     */
    private static function validIngredients(array $ingredients): array
    {
        if ([] === $ingredients || \count($ingredients) > self::MAX_INGREDIENTS) {
            throw InvalidRecipe::because(\sprintf('a recipe has 1 to %d ingredients', self::MAX_INGREDIENTS));
        }

        return $ingredients;
    }
}

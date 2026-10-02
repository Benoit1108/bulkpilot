<?php

declare(strict_types=1);

namespace App\Dev\Demo;

use App\Nutrition\Domain\Food\FoodState;
use App\Nutrition\Domain\Journal\MealSlot;

/**
 * What the demo user eats and trains: plausible, varied a little from day to day by the seeder.
 */
final class DemoPlan
{
    /**
     * Foods the user keeps (per 100 g: kcal, protein, carbohydrate, fat), so that the demo does not
     * depend on the CIQUAL import.
     *
     * @var array<string, array{FoodState, float, float, float, float}>
     */
    public const array FOODS = [
        'oats' => [FoodState::Raw, 372, 13.5, 58.7, 7.0],
        'milk' => [FoodState::NotApplicable, 46, 3.3, 4.8, 1.6],
        'banana' => [FoodState::Raw, 90, 1.1, 20.5, 0.3],
        'rice' => [FoodState::Cooked, 130, 2.7, 28.2, 0.3],
        'chicken' => [FoodState::Cooked, 165, 31.0, 0.0, 3.6],
        'olive_oil' => [FoodState::NotApplicable, 900, 0.0, 0.0, 100.0],
        'yogurt' => [FoodState::NotApplicable, 61, 3.5, 4.7, 3.3],
        'bread' => [FoodState::NotApplicable, 265, 9.0, 49.0, 3.2],
        'pasta' => [FoodState::Cooked, 158, 5.8, 30.9, 0.9],
        'beef' => [FoodState::Cooked, 250, 26.0, 0.0, 15.0],
        'eggs' => [FoodState::Cooked, 155, 13.0, 1.1, 11.0],
        'gainer' => [FoodState::NotApplicable, 380, 20.0, 65.0, 4.0],
    ];

    /** @var array<string, string> */
    public const array FOOD_NAMES = [
        'oats' => 'Flocons d’avoine', 'milk' => 'Lait demi-écrémé', 'banana' => 'Banane', 'rice' => 'Riz blanc cuit',
        'chicken' => 'Blanc de poulet cuit', 'olive_oil' => 'Huile d’olive', 'yogurt' => 'Yaourt nature', 'bread' => 'Pain complet',
        'pasta' => 'Pâtes cuites', 'beef' => 'Bœuf haché 15 % cuit', 'eggs' => 'Œufs durs', 'gainer' => 'Gainer maison',
    ];

    /**
     * The usual meals, grams per food.
     *
     * @var list<array{MealSlot, array<string, float>}>
     */
    public const array MEALS = [
        [MealSlot::Breakfast, ['oats' => 80, 'milk' => 300, 'banana' => 120]],
        [MealSlot::Lunch, ['rice' => 250, 'chicken' => 170, 'olive_oil' => 10]],
        [MealSlot::Snack, ['yogurt' => 150, 'bread' => 60]],
        [MealSlot::Gainer, ['gainer' => 70, 'milk' => 250]],
        [MealSlot::Dinner, ['pasta' => 250, 'beef' => 140, 'eggs' => 50]],
    ];

    /**
     * A push / pull / legs split, by library exercise name: sets, rep range, starting load.
     *
     * @var list<array{string, list<array{string, int, int, int, float}>}>
     */
    public const array PROGRAM = [
        ['Push', [
            ['Développé couché à la barre', 4, 6, 10, 60.0],
            ['Développé incliné aux haltères', 3, 8, 12, 22.0],
            ['Développé militaire à la barre', 3, 6, 10, 40.0],
            ['Élévations latérales aux haltères', 3, 12, 15, 8.0],
            ['Extension triceps à la corde', 3, 10, 15, 25.0],
        ]],
        ['Pull', [
            ['Tractions pronation', 4, 6, 10, 0.0],
            ['Rowing barre', 4, 8, 12, 60.0],
            ['Tirage horizontal à la poulie', 3, 10, 12, 50.0],
            ['Face pull', 3, 12, 15, 20.0],
            ['Curl à la barre EZ', 3, 8, 12, 25.0],
        ]],
        ['Legs', [
            ['Squat barre (arrière)', 4, 6, 10, 80.0],
            ['Soulevé de terre roumain', 3, 8, 10, 70.0],
            ['Presse à cuisses', 3, 10, 12, 140.0],
            ['Leg curl assis', 3, 10, 15, 40.0],
            ['Mollets debout', 4, 10, 15, 60.0],
        ]],
    ];

    /** Added to the legs day halfway through: the program's second version. */
    public const array REVISION = ['Hip thrust à la barre', 3, 8, 12, 80.0];

    /** Training days of the week (ISO: 1 = Monday). */
    public const array TRAINING_DAYS = [1, 2, 4, 6];

    private function __construct()
    {
    }
}

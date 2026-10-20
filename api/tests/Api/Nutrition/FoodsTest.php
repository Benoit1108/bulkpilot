<?php

declare(strict_types=1);

namespace App\Tests\Api\Nutrition;

use App\Tests\Support\Api\NutritionApiTestCase;

final class FoodsTest extends NutritionApiTestCase
{
    public function testFoodsNeedASignedInUser(): void
    {
        $this->call('GET', '/api/foods?q=riz');

        self::assertResponseStatusCodeSame(401);
    }

    public function testTheCatalogueIsSearchedWithoutAccentsOrCase(): void
    {
        $this->register();

        $found = $this->call('GET', '/api/foods?q=POULET%20saute')->toArray();

        self::assertResponseIsSuccessful();
        self::assertCount(1, $found);
        self::assertSame([
            'source' => 'ciqual',
            'name' => 'Poulet, filet, sans peau, sauté/poêlé',
            'brand' => null,
            'category' => 'Viandes cuites',
            'state' => 'cooked',
            'portions' => [],
            'editable' => false,
            'barcode' => null,
            'lastGrams' => null,
        ], array_diff_key(self::array($found, '0'), ['id' => 1, 'per100g' => 1]));
        self::assertSame(30.1, self::at($found, '0.per100g.protein'));

        self::assertSame([], $this->call('GET', '/api/foods?q=r')->toArray(), 'too short to search');
        self::assertCount(1, $this->call('GET', '/api/foods?q=riz&limit=1')->toArray());
    }

    public function testCustomFoodsArePrivateToTheirOwner(): void
    {
        $this->register();
        $created = $this->call('POST', '/api/foods', [
            'name' => 'Skyr nature',
            'brand' => 'Siggi\'s',
            'energyKcal' => 63,
            'protein' => 11,
            'carbohydrate' => 4,
            'fat' => 0.2,
            'portions' => [['label' => '1 pot', 'grams' => 150]],
        ]);
        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['source' => 'custom', 'state' => 'not_applicable', 'editable' => true, 'portions' => [['label' => '1 pot', 'grams' => 150.0]]]);
        $id = self::string($created->toArray(), 'id');

        self::assertSame('Skyr nature', self::string($this->getJson('/api/foods?q=skyr'), '0.name'));
        self::assertCount(1, $this->call('GET', '/api/foods/custom')->toArray());

        $this->useDevice('other');
        $this->register('sam@example.com', 'Sam');
        self::assertSame([], $this->call('GET', '/api/foods?q=skyr')->toArray());
        $this->call('GET', '/api/foods/'.$id);
        self::assertResponseStatusCodeSame(404);
        self::assertProblemCode('nutrition.food_not_found');
        $this->call('PUT', '/api/foods/'.$id, ['name' => 'Volé', 'energyKcal' => 1, 'protein' => 0, 'carbohydrate' => 0, 'fat' => 0]);
        self::assertResponseStatusCodeSame(404);
        $this->call('DELETE', '/api/foods/'.$id);
        self::assertResponseStatusCodeSame(404);
    }

    public function testACustomFoodIsEditedThenDeleted(): void
    {
        $this->register();
        $id = self::string($this->call('POST', '/api/foods', ['name' => 'Skyr', 'energyKcal' => 63, 'protein' => 11, 'carbohydrate' => 4, 'fat' => 0.2])->toArray(), 'id');

        $this->call('PUT', '/api/foods/'.$id, ['name' => 'Skyr vanille', 'state' => 'not_applicable', 'energyKcal' => 80, 'protein' => 10, 'carbohydrate' => 9, 'fat' => 0.2, 'sugars' => 8]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['name' => 'Skyr vanille', 'per100g' => ['energyKcal' => 80.0, 'sugars' => 8.0]]);

        $this->call('DELETE', '/api/foods/'.$id);
        self::assertResponseStatusCodeSame(204);
        $this->call('GET', '/api/foods/'.$id);
        self::assertResponseStatusCodeSame(404);
    }

    public function testCatalogueFoodsAreReadOnly(): void
    {
        $this->register();
        $rice = $this->foodId('riz blanc cru');

        $this->call('GET', '/api/foods/'.$rice);
        self::assertJsonContains(['name' => 'Riz blanc, cru', 'state' => 'raw', 'editable' => false]);
        $this->call('DELETE', '/api/foods/'.$rice);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('nutrition.invalid_food');
    }

    public function testImplausibleFoodsAreRejected(): void
    {
        $this->register();

        $this->call('POST', '/api/foods', ['name' => ' ', 'energyKcal' => 1000, 'protein' => -1, 'carbohydrate' => 0, 'fat' => 0, 'state' => 'frozen', 'portions' => [['label' => '', 'grams' => 0]]]);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [
            ['propertyPath' => 'name'],
            ['propertyPath' => 'state'],
            ['propertyPath' => 'energyKcal'],
            ['propertyPath' => 'protein'],
            ['propertyPath' => 'portions[0].label'],
            ['propertyPath' => 'portions[0].grams'],
        ]]);

        $this->call('POST', '/api/foods', ['name' => 'Sans valeurs', 'protein' => 11]);
        self::assertResponseStatusCodeSame(422);
        // A blank value is not 0.
        self::assertJsonContains(['violations' => [['propertyPath' => 'energyKcal'], ['propertyPath' => 'carbohydrate'], ['propertyPath' => 'fat']]]);

        $this->call('POST', '/api/foods', ['name' => 'Impossible', 'energyKcal' => 500, 'protein' => 50, 'carbohydrate' => 50, 'fat' => 50]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('nutrition.invalid_nutrition_facts');

        $this->call('GET', '/api/foods/not-a-uuid');
        self::assertResponseStatusCodeSame(404);
    }

    public function testRecentFoodsComeFromTheJournal(): void
    {
        $this->register();
        $rice = $this->foodId('riz blanc cru');
        $egg = $this->foodId('oeuf');
        $this->logFood($rice, 100);
        $this->logFood($egg, 60, 'breakfast');
        $this->logFood($rice, 150);

        $recent = $this->call('GET', '/api/foods/recent')->toArray();
        self::assertSame(['Riz blanc, cru', 'Oeuf, cru'], array_column($recent, 'name'));
        self::assertSame([150.0, 60.0], array_column($recent, 'lastGrams'), 'the quantity logged last, to log it again in one tap');
        $found = array_column($this->call('GET', '/api/foods?q=oeuf')->toArray(), 'lastGrams');
        self::assertNotEmpty($found);
        self::assertSame([], array_filter($found, static fn (mixed $grams): bool => null !== $grams), 'only recent foods carry it');
    }
}

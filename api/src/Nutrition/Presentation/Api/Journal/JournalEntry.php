<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Journal;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;

/**
 * Journal writes. Each answers with the NutritionDay it changed (read an entry through its day).
 */
#[ApiResource(
    shortName: 'JournalEntry',
    operations: [
        new Post(
            uriTemplate: '/nutrition/entries',
            description: 'Logs a quantity of a food in a meal; the food\'s values are copied into the entry. Answers with the day.',
            input: LogFoodInput::class,
            output: NutritionDay::class,
            processor: JournalEntryProcessor::class,
        ),
        new Post(
            uriTemplate: '/nutrition/entries/batch',
            description: 'Logs several foods in one meal at once, all or nothing (a Meal Builder proposal). Answers with the day.',
            input: LogFoodsInput::class,
            output: NutritionDay::class,
            processor: JournalEntryProcessor::class,
            name: 'journal_entries_batch',
        ),
        new Post(
            uriTemplate: '/nutrition/favorite-meals/{id}/log',
            requirements: ['id' => '[0-9a-f-]{36}'],
            status: 201,
            description: 'Logs every item of a favourite meal in a meal of a day, in one go. Answers with the day.',
            input: LogFavoriteInput::class,
            output: NutritionDay::class,
            read: false,
            processor: JournalEntryProcessor::class,
            name: 'favorite_meal_log',
        ),
        new Put(
            uriTemplate: '/nutrition/entries/{id}',
            requirements: ['id' => '[0-9a-f-]{36}'],
            description: 'Corrects the quantity, or moves the entry to another meal or day; answers with that day.',
            input: CorrectEntryInput::class,
            output: NutritionDay::class,
            read: false,
            processor: JournalEntryProcessor::class,
        ),
        new Delete(
            uriTemplate: '/nutrition/entries/{id}',
            requirements: ['id' => '[0-9a-f-]{36}'],
            description: 'Removes an entry.',
            read: false,
            processor: JournalEntryProcessor::class,
        ),
    ],
)]
final readonly class JournalEntry
{
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $id,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\Persistence\Doctrine;

use App\Nutrition\Domain\Exception\JournalEntryNotFound;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\NutrientAmounts;
use App\Nutrition\Domain\Journal\DayTotals;
use App\Nutrition\Domain\Journal\JournalEntry;
use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\JournalEntryRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineJournalEntryRepository implements JournalEntryRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(JournalEntry $entry): void
    {
        $this->entityManager->persist($entry);
    }

    public function remove(JournalEntry $entry): void
    {
        $this->entityManager->remove($entry);
    }

    public function getOwnedBy(JournalEntryId $id, UserId $userId): JournalEntry
    {
        $entry = $this->entityManager->find(JournalEntry::class, $id);
        if (null === $entry || !$entry->belongsTo($userId)) {
            throw JournalEntryNotFound::withId($id->value);
        }

        return $entry;
    }

    public function ofDay(UserId $userId, \DateTimeImmutable $day): array
    {
        /** @var list<JournalEntry> */
        return $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(JournalEntry::class, 'e')
            ->where('e.ownerId = :owner')
            ->andWhere('e.day = :day')
            ->orderBy('e.loggedAt')
            ->addOrderBy('e.id')
            ->setParameter('owner', $userId, 'user_id')
            ->setParameter('day', $day, 'date_immutable')
            ->getQuery()
            ->getResult();
    }

    public function dailyTotals(UserId $userId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT day,
                    SUM(food_per100g_energy_kcal * grams) / 100 AS energy_kcal,
                    SUM(food_per100g_protein * grams) / 100 AS protein,
                    SUM(food_per100g_carbohydrate * grams) / 100 AS carbohydrate,
                    SUM(food_per100g_fat * grams) / 100 AS fat,
                    SUM(food_per100g_fibre * grams) / 100 AS fibre
             FROM journal_entries
             WHERE owner_id = :owner AND day BETWEEN :from AND :to
             GROUP BY day
             ORDER BY day',
            ['owner' => $userId->value, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
        );

        $totals = [];
        foreach ($rows as $row) {
            $day = \DateTimeImmutable::createFromFormat('!Y-m-d', self::string($row['day']));
            if (false === $day) {
                continue;
            }
            $totals[$day->format('Y-m-d')] = new DayTotals($day, new NutrientAmounts(
                self::float($row['energy_kcal']),
                self::float($row['protein']),
                self::float($row['carbohydrate']),
                self::float($row['fat']),
                null === $row['fibre'] ? null : self::float($row['fibre']),
            ));
        }

        return $totals;
    }

    public function recentFoods(UserId $userId, int $limit): array
    {
        $ids = $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT food_id FROM journal_entries
             WHERE owner_id = :owner AND food_id IS NOT NULL
             GROUP BY food_id
             ORDER BY MAX(logged_at) DESC, MAX(id::text) DESC -- time-ordered ids settle the same second
             LIMIT :limit',
            ['owner' => $userId->value, 'limit' => $limit],
        );

        return array_map(static fn (mixed $id): FoodId => new FoodId(self::string($id)), $ids);
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    private static function float(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}

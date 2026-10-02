<?php

declare(strict_types=1);

namespace App\Dev\Demo;

use App\Coach\Application\Analysis\AnalyseCoach;
use App\Coach\Application\Settings\ChangeCoachSettings;
use App\Coach\Domain\Settings\Personality;
use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\User\EmailAddress;
use App\Identity\Domain\User\Profile;
use App\Identity\Domain\User\Sex;
use App\Identity\Domain\User\User;
use App\Identity\Domain\User\UserRepository;
use App\Notification\Application\Check\CheckNotifications;
use App\Notification\Application\Inbox\MarkAllRead;
use App\Nutrition\Application\Favorite\SaveFavoriteMeal;
use App\Nutrition\Application\Food\CreateCustomFood;
use App\Nutrition\Application\Food\CustomFoodDetails;
use App\Nutrition\Application\Journal\LogFoods;
use App\Nutrition\Application\Journal\LogItem;
use App\Nutrition\Application\Recipe\CreateRecipe;
use App\Nutrition\Application\Recipe\RecipeDetails;
use App\Nutrition\Application\Target\SetTargets;
use App\Nutrition\Domain\Favorite\FavoriteMealId;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Nutrition\Domain\Recipe\RecipeId;
use App\Nutrition\Domain\Target\MacroTargets;
use App\Nutrition\Domain\Target\NutritionTargetId;
use App\Progression\Application\Goal\SetWeightGoal;
use App\Progression\Application\Measurement\RecordMeasurements;
use App\Progression\Application\Weight\RecordWeighIn;
use App\Progression\Domain\Goal\WeightGoalId;
use App\Progression\Domain\Measurement\MeasurementDayId;
use App\Progression\Domain\Weight\WeighInId;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Domain\Identifier\UuidGenerator;
use App\Training\Application\Exercise\ListExercises;
use App\Training\Application\Library\ImportExerciseLibrary;
use App\Training\Application\Program\ActivateProgram;
use App\Training\Application\Program\CreateProgram;
use App\Training\Application\Program\EditProgram;
use App\Training\Application\Program\ProgramDetails;
use App\Training\Application\Workout\FinishWorkout;
use App\Training\Application\Workout\RecordSet;
use App\Training\Application\Workout\StartWorkout;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Program\ProgramVersionId;
use App\Training\Domain\Workout\SetId;
use App\Training\Domain\Workout\SetKind;
use App\Training\Domain\Workout\WorkoutId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

/**
 * Builds a demo account with a long, plausible history (spec §134), day by day through the real
 * use cases: the clock is moved to each day, so every rule (days allowed, plausible times, the
 * Coach's windows) applies as if the user had lived it. Dev and test only (ADR-0032).
 *
 * @phpstan-import-type Day from ProgramDetails
 */
final class DemoSeeder
{
    public const string EMAIL = 'demo@bulkpilot.local';
    public const string PASSWORD = 'demo-bulkpilot-2026';

    /** @var array<string, FoodId> */
    private array $foods = [];
    /** @var array<string, string> exercise ids by library name */
    private array $exercises = [];
    private ?ProgramId $program = null;
    private int $version = 1;
    private int $workouts = 0;
    private bool $favorites = false;

    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
        private readonly UserRepository $users,
        private readonly PasswordHasher $passwordHasher,
        private readonly UuidGenerator $uuids,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param ?callable(int, int): void $progress called after each day (done, total)
     * @param int                       $variant  another life for another account (same shape, other numbers)
     *
     * @return array{userId: string, days: int, workouts: int}
     */
    public function seed(string $email, int $days, \DateTimeImmutable $today, ?callable $progress = null, int $variant = 0): array
    {
        mt_srand(2026 + $variant);
        $this->version = 1;
        $this->workouts = 0;
        $this->favorites = false;
        $previousClock = Clock::get();
        $today = $today->setTime(0, 0);
        $first = $today->modify(\sprintf('-%d days', $days));

        try {
            $this->at($first, '07:00');
            $userId = $this->register($email, $first);
            $this->setUp($userId, $first);
            for ($index = 0; $index < $days; ++$index) {
                $this->live($userId, $first->modify(\sprintf('+%d days', $index)), $index, $days);
                // Bounded memory over years of history: nothing is kept between days.
                $this->entityManager->clear();
                if (null !== $progress) {
                    $progress($index + 1, $days);
                }
            }
        } finally {
            Clock::set($previousClock);
        }

        return ['userId' => $userId->value, 'days' => $days, 'workouts' => $this->workouts];
    }

    /**
     * Registered and verified directly, the one use case not driven through the bus: registering
     * by the API sends the verification link by email, which a seed cannot follow (ADR-0032).
     */
    private function register(string $email, \DateTimeImmutable $day): UserId
    {
        $address = new EmailAddress($email);
        if (null !== $this->users->findByEmail($address)) {
            throw new \RuntimeException(\sprintf('An account %s already exists: the demo is seeded once (reset the database to seed it again).', $email));
        }
        $now = Clock::get()->now();
        $user = User::register(
            new UserId($this->uuids->generate()),
            $address,
            $this->passwordHasher->hash(self::PASSWORD),
            new Profile('Alex', $day, new \DateTimeImmutable('1998-04-12'), Sex::Male, 178),
            $now,
        );
        $user->verifyEmail($now);
        $this->users->save($user);
        $this->entityManager->flush();

        return $user->id();
    }

    private function setUp(UserId $userId, \DateTimeImmutable $first): void
    {
        $this->commandBus->dispatch(new ImportExerciseLibrary());
        foreach (DemoPlan::FOODS as $key => [$state, $kcal, $protein, $carbohydrate, $fat]) {
            $this->foods[$key] = new FoodId($this->uuids->generate());
            $this->commandBus->dispatch(new CreateCustomFood($this->foods[$key], $userId, new CustomFoodDetails(DemoPlan::FOOD_NAMES[$key], null, $state, $kcal, $protein, $carbohydrate, $fat)));
        }
        $this->commandBus->dispatch(new CreateRecipe(new RecipeId($this->uuids->generate()), $userId, new RecipeDetails('Riz poulet', 2, null, [
            ['foodId' => $this->foods['rice']->value, 'grams' => 400.0],
            ['foodId' => $this->foods['chicken']->value, 'grams' => 300.0],
            ['foodId' => $this->foods['olive_oil']->value, 'grams' => 15.0],
        ])));
        $this->commandBus->dispatch(new CreateRecipe(new RecipeId($this->uuids->generate()), $userId, new RecipeDetails('Porridge', 1, null, [
            ['foodId' => $this->foods['oats']->value, 'grams' => 80.0],
            ['foodId' => $this->foods['milk']->value, 'grams' => 300.0],
            ['foodId' => $this->foods['banana']->value, 'grams' => 120.0],
        ])));

        foreach ($this->queryBus->ask(new ListExercises($userId)) as $exercise) {
            $this->exercises[$exercise->name] = $exercise->id;
        }
        $this->targets($userId, $first, 0);
        $this->commandBus->dispatch(new ChangeCoachSettings($userId, Personality::Direct, 0.25));
        $program = new ProgramId($this->uuids->generate());
        $this->commandBus->dispatch(new CreateProgram($program, new ProgramVersionId($this->uuids->generate()), $userId, $this->details(false)));
        $this->commandBus->dispatch(new ActivateProgram($program, $userId));
        $this->program = $program;
    }

    /**
     * One day of the user's life, in the order of its hours (the clock only moves forward): the
     * morning weigh-in, the Sunday measurements and the Monday Coach analysis before breakfast,
     * the meals of the day, a session on training days before dinner, then the evening.
     */
    private function live(UserId $userId, \DateTimeImmutable $day, int $index, int $days): void
    {
        $weekday = (int) $day->format('N');
        $progress = $index / max(1, $days);
        if (0 === $index % 120 && $index > 0) {
            $this->at($day, '07:00');
            $this->targets($userId, $day->modify('+1 day'), intdiv($index, 120));
        }
        if (mt_rand(1, 100) <= 90) {
            $this->at($day, '07:30');
            $weight = 68 + 10 * $progress + 0.8 * sin($index / 40) + mt_rand(-40, 40) / 100;
            $this->commandBus->dispatch(new RecordWeighIn(new WeighInId($this->uuids->generate()), $userId, $day, round($weight, 1)));
        }
        if (7 === $weekday) {
            $this->at($day, '07:45');
            $this->commandBus->dispatch(new RecordMeasurements(new MeasurementDayId($this->uuids->generate()), $userId, $day, [
                'waist' => round(78 + 3 * $progress + mt_rand(-5, 5) / 10, 1),
                'chest' => round(98 + 6 * $progress + mt_rand(-5, 5) / 10, 1),
                'left_arm' => round(34 + 3 * $progress + mt_rand(-3, 3) / 10, 1),
                'right_arm' => round(34.3 + 3 * $progress + mt_rand(-3, 3) / 10, 1),
                'left_thigh' => round(56 + 4 * $progress + mt_rand(-4, 4) / 10, 1),
            ]));
        }
        if (1 === $weekday) {
            // What the user finds on opening the app on Monday morning (the week's report included).
            $this->at($day, '07:50');
            $this->commandBus->dispatch(new AnalyseCoach($userId, $day, true));
        }
        if (1 === $index) {
            $this->at($day, '08:00');
            $this->commandBus->dispatch(new SetWeightGoal(new WeightGoalId($this->uuids->generate()), $userId, 80, [72, 75, 78]));
        }
        $eats = mt_rand(1, 100) <= 88;
        if ($eats) {
            $this->eat($userId, $day, evening: false);
        }
        if (\in_array($weekday, DemoPlan::TRAINING_DAYS, true) && mt_rand(1, 100) <= 92) {
            if (1 === $this->version && $index >= intdiv($days, 2)) {
                $this->at($day, '12:00');
                $this->commandBus->dispatch(new EditProgram($this->program(), new ProgramVersionId($this->uuids->generate()), $userId, $this->details(true)));
                $this->version = 2;
            }
            $this->train($userId, $day, $progress);
        }
        if ($eats) {
            $this->eat($userId, $day, evening: true);
        }
        if (7 === $weekday) {
            $this->at($day, '21:00');
            $this->commandBus->dispatch(new CheckNotifications($userId));
        }
        if ($index === $days - 7) {
            // Older notifications were read long ago; the last week's are still new.
            $this->at($day, '21:15');
            $this->commandBus->dispatch(new MarkAllRead($userId));
        }
        if ($eats && !$this->favorites) {
            // Kept from a day really eaten: a favourite is a copy of a logged meal.
            $this->at($day, '22:00');
            $this->commandBus->dispatch(new SaveFavoriteMeal(new FavoriteMealId($this->uuids->generate()), $userId, 'Petit-déjeuner habituel', $day, MealSlot::Breakfast));
            $this->commandBus->dispatch(new SaveFavoriteMeal(new FavoriteMealId($this->uuids->generate()), $userId, 'Midi riz poulet', $day, MealSlot::Lunch));
            $this->favorites = true;
        }
    }

    /** The meals before the evening session (breakfast to gainer), or the evening ones (dinner). */
    private function eat(UserId $userId, \DateTimeImmutable $day, bool $evening): void
    {
        foreach (DemoPlan::MEALS as [$slot, $foods]) {
            if ((MealSlot::Dinner === $slot) !== $evening || (MealSlot::Gainer === $slot && mt_rand(1, 100) > 60)) {
                continue;
            }
            $this->at($day, match ($slot) {
                MealSlot::Breakfast => '08:00',
                MealSlot::Lunch => '12:30',
                MealSlot::Snack => '16:30',
                MealSlot::Gainer => '17:30',
                MealSlot::Dinner => '20:00',
            });
            $items = [];
            foreach ($foods as $food => $grams) {
                $items[] = new LogItem($this->foods[$food], round($grams * mt_rand(85, 115) / 100));
            }
            $this->commandBus->dispatch(new LogFoods($userId, $day, $slot, $items));
        }
    }

    /**
     * A session of the next day of the split, loads growing slowly (with plateaus) over the years.
     */
    private function train(UserId $userId, \DateTimeImmutable $day, float $progress): void
    {
        $dayIndex = $this->workouts % \count(DemoPlan::PROGRAM);
        $start = $this->at($day, '18:00');
        $workoutId = new WorkoutId($this->uuids->generate());
        $this->commandBus->dispatch(new StartWorkout($workoutId, $userId, $start, $this->program(), $this->version, $dayIndex));
        $minutes = 0;
        foreach ($this->dayExercises($dayIndex) as $exerciseIndex => [, $sets, $repMin, $repMax, $load]) {
            // Every few weeks a little more; a plateau now and then.
            $grown = $load > 0 ? round($load * (1 + 0.35 * $progress + mt_rand(-2, 2) / 100) * 2) / 2 : 0.0;
            for ($set = 0; $set < $sets; ++$set) {
                $minutes += 3;
                $doneAt = $start->modify(\sprintf('+%d minutes', $minutes));
                Clock::set(new MockClock($doneAt));
                $this->commandBus->dispatch(new RecordSet(
                    $workoutId,
                    $userId,
                    new SetId($this->uuids->generate()),
                    $exerciseIndex,
                    SetKind::Normal,
                    $grown > 0 ? $grown : null,
                    mt_rand($repMin, $repMax),
                    null,
                    mt_rand(1, 3),
                    $doneAt,
                ));
            }
        }
        $this->at($day, '20:00');
        $this->commandBus->dispatch(new FinishWorkout($workoutId, $userId, $start->modify(\sprintf('+%d minutes', $minutes + 5))));
        ++$this->workouts;
    }

    private function targets(UserId $userId, \DateTimeImmutable $from, int $step): void
    {
        $kcal = 2500 + 100 * min($step, 6);
        $protein = 150;
        $fat = 80;
        $this->commandBus->dispatch(new SetTargets(new NutritionTargetId($this->uuids->generate()), $userId, $from, new MacroTargets($kcal, $protein, intdiv($kcal - 4 * $protein - 9 * $fat, 4), $fat), null));
    }

    private function details(bool $revised): ProgramDetails
    {
        $days = [];
        foreach (array_keys(DemoPlan::PROGRAM) as $index) {
            $days[] = ['name' => DemoPlan::PROGRAM[$index][0], 'blocks' => array_map(
                fn (array $exercise): array => [$this->planned($exercise)],
                $this->dayExercises($index, $revised),
            )];
        }

        return new ProgramDetails('PPL Masse', $days);
    }

    /**
     * @return list<array{string, int, int, int, float}>
     */
    private function dayExercises(int $index, ?bool $revised = null): array
    {
        $exercises = DemoPlan::PROGRAM[$index][1];
        if (2 === $index && ($revised ?? 2 === $this->version)) {
            $exercises[] = DemoPlan::REVISION;
        }

        return $exercises;
    }

    /**
     * @param array{string, int, int, int, float} $exercise
     *
     * @return array{exerciseId: string, sets: int, repMin: int, repMax: int, targetRir: ?int, restSeconds: int, strategy: ProgressionStrategy, incrementKg: float, warmupSets: int, dropSet: bool, tempo: ?string, notes: ?string}
     */
    private function planned(array $exercise): array
    {
        [$name, $sets, $repMin, $repMax] = $exercise;

        return [
            'exerciseId' => $this->exercises[$name] ?? throw new \RuntimeException(\sprintf('The exercise library has no « %s ».', $name)),
            'sets' => $sets,
            'repMin' => $repMin,
            'repMax' => $repMax,
            'targetRir' => 2,
            'restSeconds' => 120,
            'strategy' => ProgressionStrategy::DoubleProgression,
            'incrementKg' => 2.5,
            'warmupSets' => 0,
            'dropSet' => false,
            'tempo' => null,
            'notes' => null,
        ];
    }

    private function program(): ProgramId
    {
        return $this->program ?? throw new \LogicException('The program is created first.');
    }

    /** Moves the clock to that time of the day (UTC) and returns it. */
    private function at(\DateTimeImmutable $day, string $time): \DateTimeImmutable
    {
        $at = new \DateTimeImmutable($day->format('Y-m-d').' '.$time.':00', new \DateTimeZone('UTC'));
        Clock::set(new MockClock($at));

        return $at;
    }
}

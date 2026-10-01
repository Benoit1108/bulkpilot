<?php

declare(strict_types=1);

namespace App\Tests\Api\Audit;

use App\Tests\Support\Api\CoachApiTestCase;

/**
 * « Activité du compte »: the important actions of the account, and who made them (spec §10).
 */
final class ActivityTest extends CoachApiTestCase
{
    public function testTheActivityIsForSignedInUsers(): void
    {
        $this->call('GET', '/api/me/activity');

        self::assertResponseStatusCodeSame(401);
    }

    public function testASignInAndATargetChangedByTheCoachAreTheUsersOwn(): void
    {
        $this->register();
        $this->signIn();
        $this->targetSince(20);
        $this->call('PUT', '/api/coach/settings', ['personality' => 'neutral', 'paceKgPerWeek' => 0.5]);
        $this->eatDays(12, 1000);
        $this->stableWeight();
        $increase = self::recommendation($this->analyse(), 'calories.increase');
        $this->decide(self::string($increase, 'id'), 'accept');

        $activity = $this->activity();
        $actions = array_column($activity, 'action');
        self::assertSame(['coach.recommendation_decided', 'nutrition.target_set'], \array_slice($actions, 0, 2), 'the latest first');
        self::assertContains('identity.signed_in', $actions);
        self::assertContains('identity.registered', $actions);
        $byCoach = $activity[1];
        self::assertSame(['coach', 2500, 2650, 'coach'], [$byCoach['source'], $byCoach['details']['previous_kcal'], $byCoach['details']['kcal'], $byCoach['details']['origin']]);

        $this->register('sam@example.com', 'Sam');
        self::assertSame(['identity.signed_in', 'identity.registered'], array_column($this->activity(), 'action'), 'Sam sees only his own');
    }

    public function testAWrongPasswordLeavesATraceForTheAccountOwner(): void
    {
        $this->register();
        $this->signIn('alex@example.com', 'not the password');
        self::assertResponseStatusCodeSame(401);

        $this->signIn();
        self::assertSame('identity.sign_in_failed', array_column($this->activity(), 'action')[1]);
    }

    public function testTheActivityComesAPageAtATime(): void
    {
        $this->register();
        for ($i = 0; $i < 3; ++$i) {
            $this->signIn();
        }
        $all = array_column($this->activity(), 'id');

        $seen = [];
        $query = '?limit=2';
        do {
            $page = $this->activity($query);
            foreach ($page as $event) {
                $seen[] = $event['id'];
            }
            $last = end($page);
            $query = false === $last ? '' : '?limit=2&before='.rawurlencode($last['occurredAt']).'&beforeId='.$last['id'];
        } while ([] !== $page);

        self::assertSame($all, $seen, 'every event once, in order, even those of the same second');
        $this->call('GET', '/api/me/activity?before=yesterday-ish');
        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @return list<array{id: string, action: string, details: array<string, mixed>, source: string, occurredAt: string}>
     */
    private function activity(string $query = ''): array
    {
        $response = $this->call('GET', '/api/me/activity'.$query);
        self::assertResponseIsSuccessful();

        /** @var list<array{id: string, action: string, details: array<string, mixed>, source: string, occurredAt: string}> */
        return $response->toArray();
    }
}

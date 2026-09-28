<?php

declare(strict_types=1);

namespace App\Tests\Api\Identity;

use App\Tests\Support\Api\IdentityApiTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * FrankenPHP keeps one kernel per worker: requests of different users run on the same service
 * instances, with only `services_resetter` in between (what the worker loop does after
 * kernel.terminate). Nothing of one request may survive into the next.
 */
final class WorkerModeTest extends IdentityApiTestCase
{
    public function testTwoUsersAlternatingOnOneKernelNeverSeeEachOther(): void
    {
        $this->client->disableReboot();
        $this->register('ana@example.com', 'Ana');
        $this->useDevice('bob');
        $this->register('bob@example.com', 'Bob');

        for ($round = 0; $round < 20; ++$round) {
            foreach (['laptop' => 'ana@example.com', 'bob' => 'bob@example.com'] as $device => $email) {
                $this->useDevice($device);
                $this->call('GET', '/api/me');
                self::assertResponseIsSuccessful();
                self::assertJsonContains(['email' => $email], message: \sprintf('round %d, %s', $round, $device));
                $this->resetServicesLikeTheWorkerLoop();
            }
        }

        $this->useDevice('anonymous');
        $this->call('GET', '/api/me');
        self::assertResponseStatusCodeSame(401, 'an anonymous request after both users is anonymous');
    }

    public function testNoTokenSurvivesTheResetBetweenRequests(): void
    {
        $this->client->disableReboot();
        $this->register();
        $this->call('GET', '/api/me');
        self::assertResponseIsSuccessful();

        $this->resetServicesLikeTheWorkerLoop();

        self::assertNull(self::getContainer()->get(TokenStorageInterface::class)->getToken());
    }

    private function resetServicesLikeTheWorkerLoop(): void
    {
        self::getContainer()->get('services_resetter')->reset();
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Api\Identity;

use App\Tests\Support\Api\IdentityApiTestCase;

final class ProfileAndDevicesTest extends IdentityApiTestCase
{
    public function testTheProfileIsReplacedAndReturned(): void
    {
        $this->register();

        $this->call('PUT', '/api/me/profile', ['firstName' => 'Alexandre', 'birthDate' => '2000-01-15', 'sex' => 'male', 'heightCm' => 178]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['firstName' => 'Alexandre', 'birthDate' => '2000-01-15', 'sex' => 'male', 'heightCm' => 178]);
        $this->call('GET', '/api/me');
        self::assertJsonContains(['birthDate' => '2000-01-15', 'heightCm' => 178]);

        $this->call('PUT', '/api/me/profile', ['firstName' => 'Alexandre']);
        self::assertJsonContains(['birthDate' => null, 'sex' => null, 'heightCm' => null]);
    }

    public function testImplausibleProfilesAreRejected(): void
    {
        $this->register();

        $this->call('PUT', '/api/me/profile', ['firstName' => 'Alex', 'heightCm' => 40, 'sex' => 'other']);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'sex'], ['propertyPath' => 'heightCm']]]);

        $this->call('PUT', '/api/me/profile', ['firstName' => 'Alex', 'birthDate' => '2020-01-01']);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('identity.invalid_profile');
    }

    public function testDevicesAreListedAndCanBeSignedOutRemotely(): void
    {
        $this->register();
        $this->useDevice('phone');
        $this->signIn();

        $sessions = $this->listSessions();
        self::assertCount(2, $sessions);
        self::assertSame(['Firefox · Linux', 'Firefox · Linux'], array_column($sessions, 'deviceLabel'));
        $others = array_values(array_filter($sessions, static fn (array $s): bool => !$s['current']));
        self::assertCount(1, $others, 'exactly one entry is this device');
        $laptop = $others[0]['id'];

        $this->call('DELETE', '/api/me/sessions/'.$laptop);
        self::assertResponseStatusCodeSame(204);
        self::assertCount(1, $this->listSessions());

        $this->useDevice('laptop');
        $this->call('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testNobodyCanSeeOrRevokeSomeoneElsesDevices(): void
    {
        $this->register('alex@example.com');
        $alexSession = $this->listSessions()[0]['id'];

        $this->useDevice('sam');
        $this->register('sam@example.com', 'Sam');
        $samSessions = $this->listSessions();
        self::assertCount(1, $samSessions);
        self::assertNotSame($alexSession, $samSessions[0]['id']);

        $this->call('DELETE', '/api/me/sessions/'.$alexSession);
        self::assertResponseStatusCodeSame(404);
        self::assertProblemCode('identity.session_not_found');
        $this->call('DELETE', '/api/me/sessions/not-a-uuid');
        self::assertResponseStatusCodeSame(404);

        $this->useDevice('laptop');
        $this->call('GET', '/api/me');
        self::assertResponseIsSuccessful("Alex's device is untouched");
    }
}

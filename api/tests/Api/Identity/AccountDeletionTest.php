<?php

declare(strict_types=1);

namespace App\Tests\Api\Identity;

use App\Identity\Application\Deletion\EraseAccount;
use App\Identity\Application\Deletion\EraseDueAccounts;
use App\Identity\Infrastructure\Mailer\DeferredMailer;
use App\Tests\Support\Api\ProgressionApiTestCase;
use App\Tests\Support\Api\RunsTheWorker;
use Doctrine\DBAL\Connection;
use OTPHP\TOTP;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Mime\Email;

/**
 * « Supprimer mon compte » (spec §103, ADR-0030): closed at once, erased 7 days later unless
 * the user signs in again.
 */
final class AccountDeletionTest extends ProgressionApiTestCase
{
    use RunsTheWorker;

    public function testTheAccountIsClosedAtOnceOnEveryDevice(): void
    {
        $this->registerVerified();
        $this->useDevice('phone');
        $this->signIn();
        $this->useDevice('laptop');

        $this->call('POST', '/api/me/deletion', ['password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(202);
        self::assertJsonEquals(['deletionDueAt' => '2026-10-07T10:00:00+00:00']);
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertStringContainsString('supprimé définitivement le 7 octobre', (string) $message->getTextBody());
        $this->call('GET', '/api/me');
        self::assertResponseStatusCodeSame(401, 'this device is signed out');
        $this->useDevice('phone');
        $this->call('GET', '/api/me');
        self::assertResponseStatusCodeSame(401, 'every other one too');
    }

    public function testAWrongPasswordOrAMissingSecondFactorIsRefused(): void
    {
        $this->registerVerified();
        $this->call('POST', '/api/me/deletion', ['password' => 'wrong']);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('identity.wrong_current_password');

        $secret = $this->enableTwoFactor();
        $this->call('POST', '/api/me/deletion', ['password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('identity.invalid_second_factor_code');
        $this->call('GET', '/api/me');
        self::assertResponseIsSuccessful('still signed in: nothing happened');

        $this->call('POST', '/api/me/deletion', ['password' => self::PASSWORD, 'code' => self::codeAt($secret, 30)]);
        self::assertResponseStatusCodeSame(202);
    }

    public function testSigningInAgainCancelsTheDeletionAndSaysSoOnce(): void
    {
        $this->registerVerified();
        $this->call('POST', '/api/me/deletion', ['password' => self::PASSWORD]);
        self::mockTime('2026-10-03 10:00:00');

        $this->signIn();
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['twoFactorRequired' => false, 'deletionCancelled' => true]);
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertStringContainsString('annulée', (string) $message->getSubject());

        $this->call('POST', '/api/auth/sign-out');
        $this->signIn();
        self::assertJsonContains(['deletionCancelled' => false]);

        self::mockTime('2026-10-08 10:00:00');
        $this->eraseDueAccounts();
        $this->call('GET', '/api/me');
        self::assertResponseIsSuccessful('kept');
    }

    public function testWithTwoFactorOnlyTheCompleteSignInCancelsAndAWrongPasswordNothing(): void
    {
        $this->registerVerified();
        $secret = $this->enableTwoFactor();
        $this->call('POST', '/api/me/deletion', ['password' => self::PASSWORD, 'code' => self::codeAt($secret, 30)]);
        self::assertResponseStatusCodeSame(202);
        self::mockTime('2026-10-03 10:00:00');

        $this->signIn('alex@example.com', 'not the password');
        self::assertResponseStatusCodeSame(401);
        $this->signIn();
        self::assertJsonContains(['twoFactorRequired' => true, 'deletionCancelled' => false]);
        $done = $this->call('POST', '/api/auth/sign-in/second-factor', ['code' => self::codeAt($secret, 0)]);
        self::assertResponseIsSuccessful();
        self::assertTrue($done->toArray()['deletionCancelled'], 'the sign-in is complete only now');

        $activity = array_column($this->getJson('/api/me/activity'), 'action');
        self::assertContains('identity.sign_in_failed', $activity, 'the wrong password is in the activity');
        self::assertContains('identity.deletion_cancelled', $activity);
    }

    public function testAWrongConfirmationIsInTheActivity(): void
    {
        $this->registerVerified();
        $this->call('POST', '/api/me/deletion', ['password' => 'not the password']);
        self::assertResponseStatusCodeSame(422);

        self::assertContains('identity.reauthentication_failed', array_column($this->getJson('/api/me/activity'), 'action'));
    }

    public function testAfterSevenDaysEverythingOfTheUserIsErasedAndTheEmailCanRegisterAgain(): void
    {
        $this->registerVerified('sam@example.com');
        $this->weigh(self::TODAY, 70.0);
        $this->call('POST', '/api/auth/sign-out');
        $this->registerVerified();
        $this->weigh(self::TODAY, 51.2);
        $this->call('POST', '/api/me/deletion', ['password' => self::PASSWORD]);

        self::mockTime('2026-10-07 09:59:00');
        self::assertSame(0, $this->eraseDueAccounts(), 'not before the 7 days');

        self::mockTime('2026-10-07 10:00:00');
        self::assertSame(1, $this->eraseDueAccounts());

        $db = self::getContainer()->get(Connection::class);
        self::assertSame(['sam@example.com'], $db->fetchFirstColumn('SELECT email FROM users'), 'only Sam is left');
        self::assertSame(['70'], $db->fetchFirstColumn('SELECT weight_kg::text FROM progression_weigh_ins'), 'with their weigh-in');
        self::assertSame([], $db->fetchFirstColumn('SELECT s.id FROM user_sessions s LEFT JOIN users u ON u.id = s.user_id WHERE u.id IS NULL'), 'no session left behind');
        $goodbye = self::getMailerMessages();
        $last = end($goodbye);
        self::assertInstanceOf(Email::class, $last);
        self::assertSame('alex@example.com', $last->getTo()[0]->getAddress());
        self::assertStringContainsString('supprimé', (string) $last->getSubject());

        $this->register();
        $this->signIn();
        self::assertResponseIsSuccessful('a new account with the same email');
    }

    /**
     * Runs Identity's schedule, then the erasures it queued, as the worker would.
     *
     * @return int the erasures queued
     */
    private function eraseDueAccounts(): int
    {
        $container = self::getContainer();
        /** @var InMemoryTransport $async */
        $async = $container->get('messenger.transport.async');
        $async->reset();
        $bus = $container->get('command.bus');
        $bus->dispatch(new EraseDueAccounts());
        $erasures = \count(array_filter($async->getSent(), static fn (Envelope $envelope): bool => $envelope->getMessage() instanceof EraseAccount));
        // The erasures, then what they queued once committed (the files, the backups' marker).
        $this->runTheWorker();
        $container->get(DeferredMailer::class)->flush();

        return $erasures;
    }

    /** The code of the server's (mocked) time, `$ahead` seconds later. */
    private static function codeAt(string $secret, int $ahead): string
    {
        \assert('' !== $secret);

        return TOTP::createFromSecret($secret)->at(max(0, Clock::get()->now()->getTimestamp() + $ahead));
    }

    private function enableTwoFactor(): string
    {
        $setup = $this->call('POST', '/api/me/two-factor/setup', ['password' => self::PASSWORD])->toArray();
        self::assertIsString($setup['secret']);
        \assert('' !== $setup['secret']);
        $this->call('POST', '/api/me/two-factor/confirm', ['code' => self::codeAt($setup['secret'], 0)]);
        self::assertResponseIsSuccessful();

        return $setup['secret'];
    }
}

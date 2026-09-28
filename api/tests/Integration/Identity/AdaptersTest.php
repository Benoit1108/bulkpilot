<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Application\Port\IdentityMailer;
use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Application\Port\SecretGenerator;
use App\Identity\Domain\Session\UserSession;
use App\Identity\Domain\Session\UserSessionId;
use App\Identity\Domain\Session\UserSessionRepository;
use App\Identity\Domain\User\EmailAddress;
use App\Identity\Domain\User\HashedPassword;
use App\Identity\Domain\User\Profile;
use App\Identity\Domain\User\User;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Infrastructure\Security\SecurityUserProvider;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

final class AdaptersTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    public function testPasswordsAreHashedWithASaltedSlowAlgorithm(): void
    {
        $hasher = self::getContainer()->get(PasswordHasher::class);

        $first = $hasher->hash('correct horse battery');
        $second = $hasher->hash('correct horse battery');

        self::assertNotSame('correct horse battery', $first->value);
        self::assertNotSame($first->value, $second->value, 'salted');
        self::assertTrue($hasher->verify($first, 'correct horse battery'));
        self::assertFalse($hasher->verify($first, 'correct horse batterY'));
        $hasher->verifyAgainstDummy('anything');
    }

    public function testSecretsAreLongUrlSafeAndUnique(): void
    {
        $generator = self::getContainer()->get(SecretGenerator::class);

        $secrets = array_map(static fn (): string => $generator->generate(), range(1, 20));

        foreach ($secrets as $secret) {
            self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $secret);
        }
        self::assertCount(20, array_unique($secrets));
    }

    public function testEmailsAreInFrenchAndLinkToThePwa(): void
    {
        $mailer = self::getContainer()->get(IdentityMailer::class);
        $now = new \DateTimeImmutable('2026-09-28');
        $user = User::register(new UserId('0199a0b1-7c3e-7d4a-9b2f-000000000001'), new EmailAddress('alex@example.com'), new HashedPassword('h'), new Profile('Alex <b>', $now), $now);

        $mailer->sendPasswordReset($user, 'id.se+cret');
        self::assertEmailCount(0, message: 'sent after the response, not during the request');
        self::getContainer()->get(\App\Identity\Infrastructure\Mailer\DeferredMailer::class)->flush();

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('BulkPilot — Réinitialise ton mot de passe', $email->getSubject());
        self::assertStringContainsString('Bonjour Alex <b>,', (string) $email->getTextBody());
        self::assertStringContainsString('https://localhost/reinitialiser-mot-de-passe?token=id.se%2Bcret', (string) $email->getTextBody());
        self::assertStringContainsString('Bonjour Alex &lt;b&gt;,', (string) $email->getHtmlBody(), 'HTML is escaped');
    }

    public function testARevokedDeviceSessionSignsTheRequestOut(): void
    {
        $sessions = self::getContainer()->get(UserSessionRepository::class);
        $userId = new UserId('0199a0b1-7c3e-7d4a-9b2f-000000000001');
        $session = UserSession::open(new UserSessionId('0199a0b1-7c3e-7d4a-9b2f-00000000000a'), $userId, 'Firefox', new \DateTimeImmutable('-1 hour'));
        $sessions->save($session);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $provider = self::getContainer()->get(SecurityUserProvider::class);
        $securityUser = new SecurityUser($userId->value, $session->id()->value);

        self::assertSame($securityUser, $provider->refreshUser($securityUser));
        self::assertGreaterThan(new \DateTimeImmutable('-1 minute'), $session->lastSeenAt(), 'activity recorded');

        $session->revoke(new \DateTimeImmutable());
        $this->expectException(UserNotFoundException::class);
        $provider->refreshUser($securityUser);
    }

    public function testASessionOfAnotherUserOrAnUnknownIdIsRefused(): void
    {
        $provider = self::getContainer()->get(SecurityUserProvider::class);

        $refused = 0;
        foreach ([new SecurityUser('0199a0b1-7c3e-7d4a-9b2f-000000000001', '0199a0b1-7c3e-7d4a-9b2f-00000000dead'), new SecurityUser('0199a0b1-7c3e-7d4a-9b2f-000000000001', 'garbage')] as $user) {
            try {
                $provider->refreshUser($user);
            } catch (UserNotFoundException) {
                ++$refused;
            }
        }

        self::assertSame(2, $refused);
    }
}

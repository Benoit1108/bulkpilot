<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Application\PasswordReset\RequestPasswordReset;
use App\Identity\Application\PasswordReset\ResetPassword;
use App\Identity\Application\Register\RegisterUser;
use App\Identity\Application\VerifyEmail\VerifyEmail;
use App\Identity\Infrastructure\Mailer\DeferredMailer;
use App\Shared\Application\Bus\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Mime\Email;

/**
 * The security journal records who did what, never secrets or personal data.
 */
final class AuditLogTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    public function testSecurityEventsAreJournaledWithoutSensitiveData(): void
    {
        $bus = self::getContainer()->get(CommandBus::class);
        $userId = '0199a0b1-7c3e-7d4a-9b2f-000000000001';

        $bus->dispatch(new RegisterUser($userId, 'alex@example.com', 'correct horse battery', 'Alex'));
        $bus->dispatch(new VerifyEmail($this->lastLinkToken()));
        $bus->dispatch(new RequestPasswordReset('alex@example.com'));
        $resetToken = $this->lastLinkToken();
        $bus->dispatch(new ResetPassword($resetToken, 'a brand new passphrase'));

        $records = self::getContainer()->get('monolog.handler.audit_test')->getRecords();

        self::assertSame(
            ['UserRegistered', 'EmailVerified', 'PasswordChanged'],
            array_map(static fn ($record): mixed => $record->context['event'] ?? null, $records),
        );
        foreach ($records as $record) {
            self::assertSame($userId, $record->context['user_id']);
            $serialized = json_encode([$record->message, $record->context], \JSON_THROW_ON_ERROR);
            foreach (['alex@example.com', 'correct horse battery', 'a brand new passphrase', $resetToken] as $secret) {
                self::assertStringNotContainsString($secret, $serialized);
            }
        }
    }

    private function lastLinkToken(): string
    {
        self::getContainer()->get(DeferredMailer::class)->flush();
        $email = self::getMailerMessage([] === self::getMailerMessages() ? 0 : \count(self::getMailerMessages()) - 1);
        self::assertInstanceOf(Email::class, $email);
        self::assertSame(1, preg_match('/\?token=([A-Za-z0-9._%-]+)/', (string) $email->getTextBody(), $matches));

        return rawurldecode($matches[1]);
    }
}

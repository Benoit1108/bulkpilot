<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer;

use App\Identity\Application\Port\IdentityMailer;
use App\Identity\Domain\User\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Plain, short, French transactional emails. Links point to PWA routes that call the API.
 * Sent after the response (DeferredMailer).
 */
final readonly class SymfonyIdentityMailer implements IdentityMailer
{
    public function __construct(
        private DeferredMailer $mailer,
        #[Autowire(env: 'DEFAULT_URI')]
        private string $appUrl,
        #[Autowire(env: 'MAILER_FROM')]
        private string $from,
        #[Autowire(env: 'APP_DISPLAY_NAME')]
        private string $appName,
    ) {
    }

    public function sendEmailVerification(User $user, string $token): void
    {
        $this->send(
            $user,
            'Confirme ton adresse email',
            'Pour confirmer ton adresse email, ouvre ce lien (valable 7 jours) :',
            $this->link('/verifier-email', $token),
        );
    }

    public function sendPasswordReset(User $user, string $token): void
    {
        $this->send(
            $user,
            'Réinitialise ton mot de passe',
            'Pour choisir un nouveau mot de passe, ouvre ce lien (valable 1 heure). Si tu n’es pas à l’origine de cette demande, ignore cet email.',
            $this->link('/reinitialiser-mot-de-passe', $token),
        );
    }

    private function send(User $user, string $subject, string $intro, string $link): void
    {
        $greeting = \sprintf('Bonjour %s,', $user->profile()->firstName);
        $html = \sprintf(
            '<p>%s</p><p>%s</p><p><a href="%s">%s</a></p><p>— %s</p>',
            htmlspecialchars($greeting),
            htmlspecialchars($intro),
            htmlspecialchars($link),
            htmlspecialchars($link),
            htmlspecialchars($this->appName),
        );

        $this->mailer->enqueue(
            new Email()
                ->from(new Address($this->from, $this->appName))
                ->to($user->email()->value)
                ->subject(\sprintf('%s — %s', $this->appName, $subject))
                ->text(\sprintf("%s\n\n%s\n%s\n\n— %s", $greeting, $intro, $link, $this->appName))
                ->html($html),
        );
    }

    private function link(string $path, string $token): string
    {
        return rtrim($this->appUrl, '/').$path.'?token='.rawurlencode($token);
    }
}

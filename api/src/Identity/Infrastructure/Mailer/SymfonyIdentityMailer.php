<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer;

use App\Identity\Application\Port\IdentityMailer;
use App\Identity\Domain\User\EmailAddress;
use App\Identity\Domain\User\User;
use App\Shared\Domain\Text\French;
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

    public function sendDeletionScheduled(User $user, \DateTimeImmutable $dueAt): void
    {
        $this->send(
            $user,
            'Suppression de ton compte prévue',
            French::typeset(\sprintf(
                'Ton compte est fermé et tous tes appareils ont été déconnectés. Il sera supprimé définitivement %s à %s, avec toutes tes données et tes photos. Pour le garder, reconnecte-toi d’ici là. Ce n’est pas toi ? Reconnecte-toi, puis %s :',
                French::day($dueAt->format('Y-m-d')),
                $dueAt->format("H\u{202F}\\h\u{202F}i"),
                $user->hasPassword() ? 'change ton mot de passe' : 'vérifie la sécurité de ton compte Google',
            )),
            rtrim($this->appUrl, '/').'/connexion',
        );
    }

    public function sendDeletionCancelled(User $user, \DateTimeImmutable $at): void
    {
        $this->send(
            $user,
            'Suppression de ton compte annulée',
            French::typeset(\sprintf(
                'Une connexion à ton compte %s à %s a annulé sa suppression : ton compte et tes données sont gardés. Ce n’est pas toi ? %s :',
                French::day($at->format('Y-m-d')),
                $at->format("H\u{202F}\\h\u{202F}i"),
                $user->hasPassword() ? 'Change ton mot de passe' : 'Vérifie la sécurité de ton compte Google et tes appareils connectés',
            )),
            rtrim($this->appUrl, '/').'/reglages/compte',
        );
    }

    public function sendAccountErased(EmailAddress $email, string $firstName): void
    {
        $this->compose(
            $email->value,
            $firstName,
            'Ton compte a été supprimé',
            French::typeset('Ton compte et toutes tes données, photos comprises, ont été supprimés définitivement. Tu peux créer un nouveau compte à tout moment.'),
            null,
        );
    }

    private function send(User $user, string $subject, string $intro, string $link): void
    {
        $this->compose($user->email()->value, $user->profile()->firstName, $subject, $intro, $link);
    }

    private function compose(string $to, string $firstName, string $subject, string $intro, ?string $link): void
    {
        $greeting = \sprintf('Bonjour %s,', $firstName);
        $html = \sprintf(
            '<p>%s</p><p>%s</p>%s<p>— %s</p>',
            htmlspecialchars($greeting),
            htmlspecialchars($intro),
            null === $link ? '' : \sprintf('<p><a href="%s">%s</a></p>', htmlspecialchars($link), htmlspecialchars($link)),
            htmlspecialchars($this->appName),
        );

        $this->mailer->enqueue(
            new Email()
                ->from(new Address($this->from, $this->appName))
                ->to($to)
                ->subject(\sprintf('%s — %s', $this->appName, $subject))
                ->text(\sprintf("%s\n\n%s\n%s\n— %s", $greeting, $intro, null === $link ? '' : $link."\n", $this->appName))
                ->html($html),
        );
    }

    private function link(string $path, string $token): string
    {
        return rtrim($this->appUrl, '/').$path.'?token='.rawurlencode($token);
    }
}

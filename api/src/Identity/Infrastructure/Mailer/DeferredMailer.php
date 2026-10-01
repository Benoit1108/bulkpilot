<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mailer;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Sends emails once the HTTP response is on its way (kernel.terminate):
 *  - response times no longer depend on whether an email was sent (no account enumeration
 *    through a password-reset request that takes longer for existing accounts);
 *  - an SMTP hiccup does not fail the user's request; it is logged, and links can be re-requested.
 *
 * In the worker, after each message handled (its transaction committed): the worker runs for an
 * hour, and its services are reset between messages. A failed message sends nothing.
 *
 * Holds per-request state, cleared after sending and by kernel.reset (FrankenPHP worker mode).
 */
final class DeferredMailer implements ResetInterface
{
    /** @var list<Email> */
    private array $queue = [];

    public function __construct(private readonly MailerInterface $mailer, private readonly LoggerInterface $logger)
    {
    }

    public function enqueue(Email $email): void
    {
        $this->queue[] = $email;
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    #[AsEventListener(event: 'console.terminate')]
    #[AsEventListener(event: WorkerMessageHandledEvent::class)]
    public function flush(): void
    {
        $queue = $this->queue;
        $this->queue = [];

        foreach ($queue as $email) {
            try {
                $this->mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                $this->logger->error('Email could not be sent: {subject}', ['subject' => $email->getSubject(), 'exception' => $e]);
            }
        }
    }

    public function reset(): void
    {
        $this->queue = [];
    }
}

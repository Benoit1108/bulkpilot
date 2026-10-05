<?php

declare(strict_types=1);

namespace App\System\Presentation\Http;

use App\Shared\Application\Security\CurrentUser;
use App\Shared\Application\Security\NotAuthenticated;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Sentry\UserDataBag;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Errors reported during a request say whose request it was by the user's opaque id only, so that
 * one person's problem can be found again; nothing else of them (docs/MONITORING.md). Cleared at
 * the end: the FrankenPHP worker serves the next request with the same process.
 */
final readonly class ErrorReportingUser
{
    public function __construct(private CurrentUser $currentUser)
    {
    }

    /** After the firewall (priority 8) has authenticated the request. */
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 4)]
    public function identify(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        try {
            $id = $this->currentUser->id()->value;
        } catch (NotAuthenticated) {
            return;
        }
        SentrySdk::getCurrentHub()->configureScope(static function (Scope $scope) use ($id): void {
            $scope->setUser(new UserDataBag($id));
        });
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function forget(TerminateEvent $event): void
    {
        SentrySdk::getCurrentHub()->configureScope(static function (Scope $scope): void {
            $scope->removeUser();
        });
    }
}

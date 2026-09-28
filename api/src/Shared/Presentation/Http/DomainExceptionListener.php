<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http;

use App\Shared\Domain\Exception\AuthenticationFailed;
use App\Shared\Domain\Exception\Conflict;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\Exception\NotFound;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Turns business rule violations into RFC 9457 problem details carrying the stable error code
 * the PWA translates into a user message. Anything else is left to API Platform (500, no details).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
final readonly class DomainExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof DomainException || !str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $status = match (true) {
            $exception instanceof NotFound => Response::HTTP_NOT_FOUND,
            $exception instanceof Conflict => Response::HTTP_CONFLICT,
            $exception instanceof AuthenticationFailed => Response::HTTP_UNAUTHORIZED,
            default => Response::HTTP_UNPROCESSABLE_ENTITY,
        };

        $event->setResponse(new JsonResponse(
            [
                'type' => '/errors/'.$exception->errorCode(),
                'title' => Response::$statusTexts[$status],
                'status' => $status,
                'detail' => $exception->getMessage(),
                'code' => $exception->errorCode(),
            ],
            $status,
            ['Content-Type' => 'application/problem+json'],
        ));
    }
}

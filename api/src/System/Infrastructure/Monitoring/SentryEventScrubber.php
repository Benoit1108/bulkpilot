<?php

declare(strict_types=1);

namespace App\System\Infrastructure\Monitoring;

use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\Exception\Unavailable;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\ExceptionDataBag;
use Sentry\Stacktrace;
use Sentry\UserDataBag;
use Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * The last step before an error leaves for Sentry or GlitchTip (`before_send`, docs/MONITORING.md):
 * expected answers (4xx, business rules, refused sign-ins, unreadable requests) are not errors and
 * are dropped, except in the worker, where a job that finally fails is a defect whatever the cause;
 * what is sent keeps the method and the path, never the body, cookies, headers but two, query
 * string, environment, IP or e-mail; the user is only their opaque id; every text is redacted
 * (database values in SQL errors included) and stack frames carry no variables.
 */
final readonly class SentryEventScrubber
{
    /** Headers that say nothing about the person. */
    private const array KEPT_HEADERS = ['content-type', 'accept'];

    public function __invoke(Event $event, ?EventHint $hint = null): ?Event
    {
        // Tagged by the Messenger listener: the job failed for good (soft failures are not captured).
        $inWorker = isset($event->getTags()['messenger.message_class']);
        if (!$inWorker && self::isExpected($hint?->exception)) {
            return null;
        }
        $event->setRequest(self::request($event->getRequest()));
        $id = $event->getUser()?->getId();
        $event->setUser(null === $id ? null : new UserDataBag($id));
        $event->setBreadcrumb(array_map(self::breadcrumb(...), $event->getBreadcrumbs()));
        // The whole chain (previous exceptions included): every value redacted, no frame variables.
        $event->setExceptions(array_map(static function (ExceptionDataBag $exception): ExceptionDataBag {
            self::withoutVariables($exception->getStacktrace());

            return $exception->setValue(Redactor::text($exception->getValue()));
        }, $event->getExceptions()));
        self::withoutVariables($event->getStacktrace());
        if (null !== $event->getMessage()) {
            $event->setMessage(Redactor::text($event->getMessage()));
        }
        $event->setExtra(Redactor::values($event->getExtra()));
        $event->setTags(Redactor::values($event->getTags()));

        return $event;
    }

    /**
     * A refusal the API answers on purpose (404, 409, 422, 429…), or a business rule: not a defect.
     * An unavailable service (503) is kept: something outside is down.
     */
    private static function isExpected(?\Throwable $exception): bool
    {
        return match (true) {
            null === $exception => false,
            $exception instanceof Unavailable => false,
            $exception instanceof DomainException => true,
            // Not signed in, session expired, not allowed: answered 401/403 by the firewall.
            $exception instanceof AuthenticationException, $exception instanceof AccessDeniedException => true,
            // Malformed JSON or values that cannot be read: answered 400.
            $exception instanceof RequestExceptionInterface, $exception instanceof UnexpectedValueException => true,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode() < 500,
            $exception instanceof ProblemExceptionInterface => ($exception->getStatus() ?? 500) < 500,
            default => false,
        };
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private static function request(array $request): array
    {
        $kept = [];
        if (isset($request['method'])) {
            $kept['method'] = $request['method'];
        }
        if (\is_string($request['url'] ?? null)) {
            $kept['url'] = Redactor::withoutQueries($request['url']);
        }
        $headers = \is_array($request['headers'] ?? null) ? $request['headers'] : [];
        $kept['headers'] = array_filter($headers, static fn (mixed $name): bool => \is_string($name) && \in_array(strtolower($name), self::KEPT_HEADERS, true), \ARRAY_FILTER_USE_KEY);

        return $kept;
    }

    /** Local variables of a frame can hold anything the code had in hand: never sent. */
    private static function withoutVariables(?Stacktrace $stacktrace): void
    {
        foreach ($stacktrace?->getFrames() ?? [] as $frame) {
            $frame->setVars([]);
        }
    }

    /** What happened, not with what data: messages redacted, metadata (SQL parameters, URLs) dropped. */
    private static function breadcrumb(Breadcrumb $breadcrumb): Breadcrumb
    {
        $clean = new Breadcrumb($breadcrumb->getLevel(), $breadcrumb->getType(), $breadcrumb->getCategory(), null, [], $breadcrumb->getTimestamp());

        return null === $breadcrumb->getMessage() ? $clean : $clean->withMessage(Redactor::withoutQueries($breadcrumb->getMessage()));
    }
}

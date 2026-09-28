<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Messenger wraps handler exceptions; callers of our buses expect the original
 * domain/application exception so that it can be mapped to the right HTTP response.
 */
trait UnwrapsHandlerFailures
{
    /**
     * @param \Closure(): Envelope $dispatch
     */
    private function unwrap(\Closure $dispatch): Envelope
    {
        try {
            return $dispatch();
        } catch (HandlerFailedException $e) {
            $leaves = $e->getWrappedExceptions(recursive: true);

            throw 1 === \count($leaves) ? reset($leaves) : $e;
        }
    }
}

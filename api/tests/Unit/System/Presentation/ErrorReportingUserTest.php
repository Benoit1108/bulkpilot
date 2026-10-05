<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Presentation;

use App\Shared\Application\Security\CurrentUser;
use App\Shared\Application\Security\NotAuthenticated;
use App\Shared\Domain\Identifier\UserId;
use App\System\Presentation\Http\ErrorReportingUser;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ErrorReportingUserTest extends TestCase
{
    private const string ID = '0199a0b1-0000-7000-8000-00000000f001';

    protected function setUp(): void
    {
        SentrySdk::setCurrentHub(new Hub(null, new Scope()));
    }

    public function testAnErrorDuringARequestSaysWhoseItWasByIdOnlyThenIsForgotten(): void
    {
        $listener = new ErrorReportingUser(self::signedIn(new UserId(self::ID)));
        $kernel = self::createStub(HttpKernelInterface::class);

        $listener->identify(new RequestEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST));
        self::assertSame(['id' => self::ID], self::userOfTheNextEvent());

        $listener->forget(new TerminateEvent($kernel, new Request(), new Response()));
        self::assertNull(self::userOfTheNextEvent());
    }

    public function testAnAnonymousRequestHasNoUser(): void
    {
        new ErrorReportingUser(self::signedIn(null))->identify(new RequestEvent(self::createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST));

        self::assertNull(self::userOfTheNextEvent());
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function userOfTheNextEvent(): ?array
    {
        $user = null;
        SentrySdk::getCurrentHub()->configureScope(static function (Scope $scope) use (&$user): void {
            $user = $scope->applyToEvent(Event::createEvent())?->getUser();
        });

        return null === $user ? null : array_filter(['id' => $user->getId(), 'email' => $user->getEmail(), 'ip' => $user->getIpAddress()], static fn (mixed $value): bool => null !== $value);
    }

    private static function signedIn(?UserId $id): CurrentUser
    {
        return new readonly class($id) implements CurrentUser {
            public function __construct(private ?UserId $userId)
            {
            }

            public function id(): UserId
            {
                return $this->userId ?? throw new NotAuthenticated('anonymous');
            }
        };
    }
}

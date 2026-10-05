<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Infrastructure\Monitoring;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Shared\Domain\Exception\ConcurrentUpdate;
use App\System\Infrastructure\Monitoring\Redactor;
use App\System\Infrastructure\Monitoring\SentryEventScrubber;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\State\Hub;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;
use Sentry\UserDataBag;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * What leaves for error reporting (docs/MONITORING.md): where and how it broke, never who or with what.
 */
final class SentryEventScrubberTest extends TestCase
{
    private const string UUID = '0199a0b1-0000-7000-8000-00000000f001';

    public function testTheRequestKeepsItsMethodPathAndTwoHarmlessHeadersOnly(): void
    {
        $event = Event::createEvent()->setRequest([
            'method' => 'PUT',
            'url' => 'https://app.test/api/progression/weigh-ins/2026-10-02?token=secret',
            'query_string' => 'token=secret',
            'data' => ['weightKg' => 51.2],
            'cookies' => ['sid' => 'session'],
            'headers' => ['Content-Type' => ['application/json'], 'Cookie' => ['sid=session'], 'Authorization' => ['Bearer x'], 'User-Agent' => ['iPhone']],
            'env' => ['REMOTE_ADDR' => '1.2.3.4'],
        ]);

        $scrubbed = (new SentryEventScrubber())($event);

        self::assertNotNull($scrubbed);
        self::assertSame([
            'method' => 'PUT',
            'url' => 'https://app.test/api/progression/weigh-ins/2026-10-02',
            'headers' => ['Content-Type' => ['application/json']],
        ], $scrubbed->getRequest());
    }

    public function testTheUserIsTheirOpaqueIdOnly(): void
    {
        $user = new UserDataBag(self::UUID, 'alex@example.com', '1.2.3.4', 'alex');
        $scrubbed = (new SentryEventScrubber())(Event::createEvent()->setUser($user))?->getUser();

        self::assertNotNull($scrubbed);
        self::assertSame(self::UUID, $scrubbed->getId());
        self::assertNull($scrubbed->getEmail());
        self::assertNull($scrubbed->getIpAddress());
        self::assertNull($scrubbed->getUsername());
    }

    public function testTextsAreRedactedAndBreadcrumbsLoseTheirData(): void
    {
        $token = str_repeat('Ab1_', 10);
        $event = Event::createEvent()
            ->setMessage('Failed for alex@example.com')
            ->setExtra(['link' => 'https://app.test/reset?token='.$token, 'nested' => ['to' => 'sam@example.org']])
            ->setTags(['owner' => self::UUID])
            ->setBreadcrumb([
                new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'doctrine', 'SELECT * FROM users WHERE email = ?', ['params' => ['alex@example.com']]),
                new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_HTTP, 'http', 'GET https://open-food-facts.test/product?code=1', ['status_code' => 200]),
            ]);

        $scrubbed = (new SentryEventScrubber())($event);

        self::assertNotNull($scrubbed);
        self::assertSame('Failed for [email]', $scrubbed->getMessage());
        self::assertSame(['link' => 'https://app.test/reset?token=[token]', 'nested' => ['to' => '[email]']], $scrubbed->getExtra());
        self::assertSame(['owner' => self::UUID], $scrubbed->getTags());
        $breadcrumbs = $scrubbed->getBreadcrumbs();
        self::assertSame(['SELECT * FROM users WHERE email = ?', 'GET https://open-food-facts.test/product'], array_map(static fn (Breadcrumb $breadcrumb): ?string => $breadcrumb->getMessage(), $breadcrumbs));
        self::assertSame([[], []], array_map(static fn (Breadcrumb $breadcrumb): array => $breadcrumb->getMetadata(), $breadcrumbs));
    }

    public function testExpectedAnswersAreNotErrors(): void
    {
        $scrubber = new SentryEventScrubber();

        $refusals = [
            new NotFoundHttpException(), ConcurrentUpdate::create(), new ValidationException(new ConstraintViolationList()), new HttpException(429),
            new AccessDeniedException(), new AuthenticationCredentialsNotFoundException(), new BadRequestException('bad'), new NotEncodableValueException('Syntax error'),
        ];
        foreach ($refusals as $expected) {
            self::assertNull($scrubber(Event::createEvent(), EventHint::fromArray(['exception' => $expected])), $expected::class);
        }
        foreach ([new \RuntimeException('bug'), new HttpException(500), new class('down') extends \App\Shared\Domain\Exception\Unavailable {
            public function errorCode(): string
            {
                return 'test.down';
            }
        }] as $defect) {
            self::assertNotNull($scrubber(Event::createEvent(), EventHint::fromArray(['exception' => $defect])), $defect::class);
        }
    }

    public function testAJobThatFinallyFailsOnABusinessRuleIsReported(): void
    {
        $scrubber = new SentryEventScrubber();
        $inWorker = Event::createEvent()->setTags(['messenger.message_class' => 'App\\Export\\Application\\GenerateExport']);

        self::assertNotNull($scrubber($inWorker, EventHint::fromArray(['exception' => ConcurrentUpdate::create()])));
    }

    public function testDatabaseErrorsLoseTheValuesTheyQuote(): void
    {
        $message = "SQLSTATE[23505]: Unique violation: 7 ERROR:  duplicate key value violates unique constraint \"progression_weigh_ins_owner_day_key\"\nDETAIL:  Key (owner_id, day)=(".self::UUID.', 2026-10-02) already exists.';
        self::assertStringEndsWith('Key (owner_id, day)=([values]) already exists.', Redactor::text($message));
        self::assertSame('DETAIL:  Failing row contains ([values]).', Redactor::text('DETAIL:  Failing row contains ('.self::UUID.', 51.2, null, (note)).'));
        self::assertSame("An exception occurred while executing 'INSERT INTO notes VALUES (?)' with params [values]: boom", Redactor::text("An exception occurred while executing 'INSERT INTO notes VALUES (?)' with params [\"sore knee\"]: boom"));
    }

    public function testTheChainedExceptionsAreRedactedAndFramesCarryNoVariables(): void
    {
        $transport = $this->transport();
        $hub = new Hub(ClientBuilder::create(['dsn' => 'https://key@errors.test/1', 'before_send' => new SentryEventScrubber(), 'default_integrations' => false])->setTransport($transport)->getClient());

        $hub->captureException(new \RuntimeException('Export failed', 0, new \LogicException('No file for sam@example.org')));

        $exceptions = $transport->sent[0]->getExceptions();
        self::assertEqualsCanonicalizing(['Export failed', 'No file for [email]'], array_map(static fn (\Sentry\ExceptionDataBag $exception): string => $exception->getValue(), $exceptions));
        foreach ($exceptions as $exception) {
            foreach ($exception->getStacktrace()?->getFrames() ?? [] as $frame) {
                self::assertSame([], $frame->getVars());
            }
        }
    }

    public function testThroughTheSdkTheSentEventIsScrubbedAndAnExpectedOneIsNotSent(): void
    {
        $transport = $this->transport();
        $client = ClientBuilder::create(['dsn' => 'https://key@errors.test/1', 'send_default_pii' => false, 'before_send' => new SentryEventScrubber(), 'default_integrations' => false])
            ->setTransport($transport)
            ->getClient();
        $hub = new Hub($client);

        $hub->captureException(new \RuntimeException('No food for alex@example.com'));
        $hub->captureException(new NotFoundHttpException('No such page'));

        self::assertCount(1, $transport->sent);
        self::assertSame('No food for [email]', $transport->sent[0]->getExceptions()[0]->getValue());
    }

    public function testTheRedactorKeepsPathsAndIds(): void
    {
        self::assertSame('/api/workouts/'.self::UUID.'/sets/a', Redactor::text('/api/workouts/'.self::UUID.'/sets/a'));
        self::assertSame('key [token] end', Redactor::text('key '.str_repeat('f', 64).' end'));
    }

    /**
     * @return TransportInterface&object{sent: list<Event>}
     */
    private function transport(): TransportInterface
    {
        return new class implements TransportInterface {
            /** @var list<Event> */
            public array $sent = [];

            public function send(Event $event): Result
            {
                $this->sent[] = $event;

                return new Result(ResultStatus::success(), $event);
            }

            public function close(?int $timeout = null): Result
            {
                return new Result(ResultStatus::success());
            }
        };
    }
}

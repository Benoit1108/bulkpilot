<?php

declare(strict_types=1);

namespace App\System\Infrastructure\Monitoring;

use App\System\Application\Monitoring\Pings;
use App\System\Domain\Monitoring\Beat;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * healthchecks.io checks (`HEARTBEAT_URL_*`, e.g. https://hc-ping.com/<uuid>): a GET when a beat
 * happened, a POST to `<url>/fail` with the reason when it failed (healthchecks.io's ping API).
 * Outside the server, they also tell when the whole machine is silent. A beat without a URL is not
 * watched from outside (development, tests). Short timeout; a check that does not answer, or
 * answers with an error (a wrong URL: 404), is logged, never thrown at the work it watches.
 */
final readonly class HttpPings implements Pings
{
    private const float TIMEOUT_SECONDS = 3.0;
    /** healthchecks.io keeps the body as the ping's log: a short reason, never personal data. */
    private const int MAX_MESSAGE = 200;

    /** @var array<value-of<Beat>, string> */
    private array $urls;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire('%env(HEARTBEAT_URL_SCHEDULER)%')]
        string $scheduler,
        #[Autowire('%env(HEARTBEAT_URL_BACKUP)%')]
        string $backup,
        #[Autowire('%env(HEARTBEAT_URL_BACKUP_VERIFY)%')]
        string $backupVerify,
    ) {
        $this->urls = [Beat::Scheduler->value => $scheduler, Beat::Backup->value => $backup, Beat::BackupVerify->value => $backupVerify];
    }

    public function up(Beat $beat): void
    {
        $this->send($beat, 'GET', '', []);
    }

    public function down(Beat $beat, string $reason): void
    {
        $this->send($beat, 'POST', '/fail', ['body' => mb_substr($reason, 0, self::MAX_MESSAGE)]);
    }

    /**
     * @param array{body?: string} $options
     */
    private function send(Beat $beat, string $method, string $suffix, array $options): void
    {
        $url = rtrim($this->urls[$beat->value], '/');
        if ('' === $url) {
            return;
        }
        try {
            $status = $this->httpClient->request($method, $url.$suffix, $options + ['timeout' => self::TIMEOUT_SECONDS, 'max_duration' => self::TIMEOUT_SECONDS])->getStatusCode();
        } catch (ExceptionInterface $failure) {
            $this->logger->warning('system.heartbeat_not_sent', ['beat' => $beat->value, 'reason' => $failure->getMessage()]);

            return;
        }
        if ($status < 200 || $status >= 300) {
            $this->logger->warning('system.heartbeat_not_sent', ['beat' => $beat->value, 'reason' => \sprintf('HTTP %d', $status)]);
        }
    }
}

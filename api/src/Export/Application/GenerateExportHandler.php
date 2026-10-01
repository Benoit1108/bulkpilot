<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportRequest;
use App\Export\Domain\ExportRequestRepository;
use App\Shared\Application\Accounts\Accounts;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Job\JobHandler;
use App\Shared\Application\File\TemporaryFile;
use App\Shared\Application\Media\Archives;
use App\Shared\Application\PersonalData\ExportScope;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UuidGenerator;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Takes the export, reads the user's data from every context in the scope (no transaction: a
 * failed read aborts nothing), writes the archive on disk, then keeps it and makes the export
 * ready. Any failure marks it failed (the user asks again) rather than retrying a heavy job; a
 * worker dying half-way leaves it open, failed later by the export schedule.
 */
final readonly class GenerateExportHandler implements JobHandler
{
    private const string TEMPORARY = 'bulkpilot-export-';
    /** The account's zone when it cannot be read (its deletion is pending). */
    private const string DEFAULT_ZONE = 'Europe/Paris';

    public function __construct(
        private CommandBus $commandBus,
        private ExportRequestRepository $exports,
        private ExportDomains $domains,
        private ExportArchive $archive,
        private Archives $archives,
        private Accounts $accounts,
        private UuidGenerator $uuids,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(GenerateExport $job): void
    {
        try {
            $this->commandBus->dispatch(new StartExport($job->exportId));
        } catch (ExportTaken) {
            return;
        }
        $export = $this->exports->find($job->exportId);
        if (null === $export) {
            return;
        }
        TemporaryFile::removeStale(self::TEMPORARY, $this->clock->now());
        $path = TemporaryFile::create(self::TEMPORARY);
        $archiveId = new MediaId($this->uuids->generate());
        try {
            $this->write($export, $path);
            $this->commandBus->dispatch(new CompleteExport($export->id(), $archiveId, $path));
        } catch (\Throwable $failure) {
            $this->logger->error('Export {id} failed: {message}', ['id' => $export->id()->value, 'message' => $failure->getMessage(), 'exception' => $failure]);
            // The archive may have been written before its record failed to commit.
            $this->archives->forget($archiveId);
            $this->commandBus->dispatch(new FailExport($export->id()));
        } finally {
            TemporaryFile::remove($path);
        }
    }

    private function write(ExportRequest $export, string $path): void
    {
        $zone = $this->accounts->clockOf($export->ownerId())->timeZone ?? self::DEFAULT_ZONE;
        $scope = new ExportScope($export->fromDay(), $export->toDay(), $export->domains(), $zone);
        $datasets = [];
        $files = [];
        foreach ($this->domains->exportable() as $source) {
            if (!$scope->covers($source->domain())) {
                continue;
            }
            $datasets[$source->domain()] = $source->export($export->ownerId(), $scope);
            array_push($files, ...$source->files($export->ownerId(), $scope));
        }
        $titles = array_intersect_key($this->domains->titles(), $datasets);

        $this->archive->write($path, new ExportContents($this->clock->now(), $export->fromDay(), $export->toDay(), $titles, $zone), $datasets, $export->ownerId(), $files);
    }
}

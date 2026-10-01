<?php

declare(strict_types=1);

namespace App\Export\Presentation\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Export\Application\DownloadExport;
use App\Export\Application\GetExportLink;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Http\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<null, ExportDownload>
 */
final readonly class ExportDownloadProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private RateLimiterFactoryInterface $exportDownloadsLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ExportDownload
    {
        $userId = $this->currentUser->id();
        RateLimit::consume($this->exportDownloadsLimiter, 'export_downloads|'.$userId->value);
        $id = ExportProvider::id($uriVariables['id'] ?? null);
        $this->commandBus->dispatch(new DownloadExport($id, $userId));

        return new ExportDownload($id->value, $this->queryBus->ask(new GetExportLink($id, $userId)));
    }
}

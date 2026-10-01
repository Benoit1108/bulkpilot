<?php

declare(strict_types=1);

namespace App\Export\Presentation\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Export\Application\GetExport;
use App\Export\Application\RequestExport;
use App\Export\Domain\ExportId;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Domain\Identifier\UuidGenerator;
use App\Shared\Presentation\Api\CalendarDays;
use App\Shared\Presentation\Http\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<ExportInput, Export>
 */
final readonly class ExportProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private UuidGenerator $uuids,
        private RateLimiterFactoryInterface $exportWritesLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Export
    {
        $userId = $this->currentUser->id();
        // Only an export actually asked for takes a token: a refused one (invalid, one in progress) does not.
        RateLimit::ensureAvailable($this->exportWritesLimiter, 'export_writes|'.$userId->value);
        $id = new ExportId($this->uuids->generate());
        $this->commandBus->dispatch(new RequestExport($id, $userId, CalendarDays::parse($data->from), CalendarDays::parse($data->to), $data->domains));
        RateLimit::take($this->exportWritesLimiter, 'export_writes|'.$userId->value);

        return Export::of($this->queryBus->ask(new GetExport($id, $userId)));
    }
}

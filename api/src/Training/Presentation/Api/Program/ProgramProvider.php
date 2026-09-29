<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Program;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\Ids;
use App\Training\Application\Program\GetProgram;
use App\Training\Application\Program\ListPrograms;
use App\Training\Domain\Exception\ProgramNotFound;
use App\Training\Domain\Program\ProgramId;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<Program>
 */
final readonly class ProgramProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    /**
     * @return Program|list<Program>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Program|array
    {
        $userId = $this->currentUser->id();
        if ($operation instanceof CollectionOperationInterface) {
            return array_map(Program::fromView(...), $this->queryBus->ask(new ListPrograms($userId)));
        }
        $request = $context['request'] ?? null;
        $version = $request instanceof Request ? $request->query->getInt('version') : 0;

        return Program::fromView($this->queryBus->ask(new GetProgram($userId, self::programId($uriVariables['id'] ?? null), $version > 0 ? $version : null)));
    }

    public static function programId(mixed $id): ProgramId
    {
        return Ids::parse($id, ProgramId::class, ProgramNotFound::withId(...));
    }
}

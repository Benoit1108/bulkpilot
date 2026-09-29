<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Photo;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Progression\Application\Photo\AddProgressPhoto;
use App\Progression\Application\Photo\GetProgressPhoto;
use App\Progression\Application\Photo\RemoveProgressPhoto;
use App\Progression\Domain\Exception\InvalidProgressPhoto;
use App\Progression\Domain\Photo\Pose;
use App\Progression\Domain\Photo\ProgressPhotoId;
use App\Progression\Presentation\Api\ProgressionDays;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Media\Photos;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UuidGenerator;
use App\Shared\Presentation\Http\RateLimit;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<null, ProgressPhoto|null>
 */
final readonly class ProgressPhotoProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private UuidGenerator $uuids,
        private RateLimiterFactoryInterface $progressionWritesLimiter,
        private Photos $photos,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ProgressPhoto
    {
        $userId = $this->currentUser->id();
        RateLimit::consume($this->progressionWritesLimiter, 'progression_writes|'.$userId->value);

        if ($operation instanceof DeleteOperationInterface) {
            $this->commandBus->dispatch(new RemoveProgressPhoto($userId, ProgressPhotoProvider::photoId($uriVariables['id'] ?? null)));

            return null;
        }

        $request = $context['request'] ?? null;
        \assert($request instanceof Request);
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw InvalidProgressPhoto::because(\UPLOAD_ERR_INI_SIZE === ($file instanceof UploadedFile ? $file->getError() : null) ? 'a photo weighs at most 15 MB' : 'a photo is sent as "file"');
        }
        $pose = Pose::tryFrom($request->request->getString('pose')) ?? throw InvalidProgressPhoto::because('a pose is front, side, back or other');
        $photoId = new ProgressPhotoId($this->uuids->generate());
        $mediaId = new MediaId($this->uuids->generate());
        try {
            $this->commandBus->dispatch(new AddProgressPhoto($photoId, $mediaId, $userId, ProgressionDays::parse($request->request->getString('day')), $pose, $file->getPathname()));
        } catch (\Throwable $failure) {
            // The files may be stored while the records rolled back: none may stay behind (ADR-0027).
            $this->photos->forget($mediaId);

            throw $failure;
        }

        return ProgressPhoto::of($this->queryBus->ask(new GetProgressPhoto($userId, $photoId)));
    }
}

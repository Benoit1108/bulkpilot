<?php

declare(strict_types=1);

namespace App\Progression\Application\Photo;

use App\Progression\Domain\Photo\ProgressPhotoRepository;
use App\Shared\Application\Bus\Query\QueryHandler;
use App\Shared\Application\Media\Photos;

final readonly class GetProgressPhotoHandler implements QueryHandler
{
    public function __construct(private ProgressPhotoRepository $photos, private Photos $media)
    {
    }

    public function __invoke(GetProgressPhoto $query): PhotoView
    {
        $photo = $this->photos->getOwnedBy($query->photoId, $query->userId);

        return PhotoView::of($photo, $this->media->links($query->userId, $photo->mediaId(), PhotoView::downloadName($photo)));
    }
}

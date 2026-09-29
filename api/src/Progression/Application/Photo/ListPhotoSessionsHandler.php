<?php

declare(strict_types=1);

namespace App\Progression\Application\Photo;

use App\Progression\Application\Measurement\MeasurementDayView;
use App\Progression\Domain\Measurement\CustomMeasureRepository;
use App\Progression\Domain\Measurement\MeasurementDay;
use App\Progression\Domain\Measurement\MeasurementDayRepository;
use App\Progression\Domain\Photo\ProgressPhoto;
use App\Progression\Domain\Photo\ProgressPhotoRepository;
use App\Progression\Domain\Weight\DayWeight;
use App\Progression\Domain\Weight\WeighIn;
use App\Progression\Domain\Weight\WeighInRepository;
use App\Shared\Application\Bus\Query\QueryHandler;
use App\Shared\Application\Media\Photos;

final readonly class ListPhotoSessionsHandler implements QueryHandler
{
    public function __construct(
        private ProgressPhotoRepository $photos,
        private Photos $media,
        private WeighInRepository $weighIns,
        private MeasurementDayRepository $measurements,
        private CustomMeasureRepository $measures,
    ) {
    }

    /**
     * @return list<PhotoSessionView> the most recent day first
     */
    public function __invoke(ListPhotoSessions $query): array
    {
        $days = [];
        $downloadNames = [];
        foreach ($this->photos->ofUser($query->userId) as $photo) {
            $days[$photo->day()->format('Y-m-d')][] = $photo;
            $downloadNames[$photo->mediaId()->value] = PhotoView::downloadName($photo);
        }
        if ([] === $days) {
            return [];
        }
        // Read once each, then walked alongside the sessions (a few hundred rows at most).
        $weighIns = $this->weighIns->since($query->userId, null);
        $measured = $this->measurements->ofUser($query->userId);
        $names = [];
        foreach ($this->measures->ofUser($query->userId, withArchived: true) as $measure) {
            $names[$measure->key()] = $measure->name();
        }

        $links = $this->media->linksFor($query->userId, $downloadNames);

        $sessions = [];
        foreach ($days as $photos) {
            $day = $photos[0]->day();
            $weighIn = self::lastOnOrBefore($weighIns, $day);
            $measurements = self::lastOnOrBefore($measured, $day);
            $sessions[] = new PhotoSessionView(
                $day,
                $weighIn instanceof WeighIn ? DayWeight::of($weighIn) : null,
                $measurements instanceof MeasurementDay ? MeasurementDayView::of($measurements) : null,
                // A photo whose file is gone (being deleted) is left out.
                array_values(array_filter(array_map(static fn (ProgressPhoto $photo): ?PhotoView => isset($links[$photo->mediaId()->value]) ? PhotoView::of($photo, $links[$photo->mediaId()->value]) : null, $photos))),
                array_intersect_key($names, $measurements instanceof MeasurementDay ? $measurements->values() : []),
            );
        }

        return $sessions;
    }

    /**
     * @template T of WeighIn|MeasurementDay
     *
     * @param list<T> $records the oldest first
     *
     * @return T|null the last one of that day or before
     */
    private static function lastOnOrBefore(array $records, \DateTimeImmutable $day): WeighIn|MeasurementDay|null
    {
        $found = null;
        foreach ($records as $record) {
            if ($record->day() > $day) {
                break;
            }
            $found = $record;
        }

        return $found;
    }
}

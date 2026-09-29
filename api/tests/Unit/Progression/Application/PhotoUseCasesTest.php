<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\Application;

use App\Progression\Application\Measurement\RecordMeasurements;
use App\Progression\Application\Measurement\RecordMeasurementsHandler;
use App\Progression\Application\Photo\AddProgressPhoto;
use App\Progression\Application\Photo\AddProgressPhotoHandler;
use App\Progression\Application\Photo\DeleteRemovedPhotoFile;
use App\Progression\Application\Photo\GetProgressPhoto;
use App\Progression\Application\Photo\GetProgressPhotoHandler;
use App\Progression\Application\Photo\ListPhotoSessions;
use App\Progression\Application\Photo\ListPhotoSessionsHandler;
use App\Progression\Application\Photo\PhotoView;
use App\Progression\Application\Photo\RemoveProgressPhoto;
use App\Progression\Application\Photo\RemoveProgressPhotoHandler;
use App\Progression\Domain\Exception\InvalidProgressPhoto;
use App\Progression\Domain\Exception\ProgressPhotoNotFound;
use App\Progression\Domain\Measurement\MeasurementDayId;
use App\Progression\Domain\Photo\Pose;
use App\Progression\Domain\Photo\ProgressPhoto;
use App\Progression\Domain\Photo\ProgressPhotoId;
use App\Progression\Domain\Photo\ProgressPhotoRemoved;
use App\Shared\Application\Media\InvalidPhoto;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Progression\FakePhotos;
use App\Tests\Support\Progression\InMemoryProgressPhotoRepository;
use App\Tests\Support\Progression\ProgressionTestCase;

final class PhotoUseCasesTest extends ProgressionTestCase
{
    private InMemoryProgressPhotoRepository $photos;
    private FakePhotos $media;

    protected function setUp(): void
    {
        parent::setUp();
        $this->photos = new InMemoryProgressPhotoRepository();
        $this->media = new FakePhotos();
    }

    private function add(string $day, Pose $pose = Pose::Front, string $path = 'photo.jpg', ?UserId $userId = null): string
    {
        $id = new ProgressPhotoId($this->uuids->generate());
        new AddProgressPhotoHandler($this->photos, $this->media, $this->clock)(new AddProgressPhoto($id, new MediaId($this->uuids->generate()), $userId ?? $this->alex, self::day($day), $pose, $path));

        return $id->value;
    }

    public function testAPhotoKeepsItsDayPoseAndSize(): void
    {
        $id = $this->add('2026-09-29', Pose::Side);

        $view = new GetProgressPhotoHandler($this->photos, $this->media)(new GetProgressPhoto($this->alex, new ProgressPhotoId($id)));
        self::assertSame(['2026-09-29', Pose::Side, 1200, 1600], [$view->day->format('Y-m-d'), $view->pose, $view->width, $view->height]);
        self::assertStringEndsWith('download=photo-2026-09-29-side.webp', $view->links->downloadUrl, 'a neutral name');
    }

    public function testNothingIsStoredForADayToComeNorPastTheLimit(): void
    {
        try {
            $this->add('2026-10-01');
            self::fail('a day to come');
        } catch (InvalidProgressPhoto) {
            self::assertSame([], $this->media->stored, 'refused before the file is stored');
        }

        for ($i = 0; $i < ProgressPhoto::MAX_PER_USER; ++$i) {
            $this->photos->save(ProgressPhoto::add(new ProgressPhotoId($this->uuids->generate()), $this->alex, new MediaId($this->uuids->generate()), self::day('2026-09-01'), Pose::Front, 10, 10, $this->clock->now()));
        }
        $this->expectException(InvalidProgressPhoto::class);
        $this->add('2026-09-29');
    }

    public function testAFileMediaRefusesLeavesNoPhoto(): void
    {
        $this->expectException(InvalidPhoto::class);
        try {
            $this->add('2026-09-29', path: 'bad');
        } finally {
            self::assertSame([], $this->photos->photos);
        }
    }

    public function testSessionsGroupADaysPhotosWithTheWeightAndMeasurementsOfThatDayOrBefore(): void
    {
        $this->weigh(['2026-06-01' => 45.0, '2026-09-27' => 51.0, '2026-09-29' => 51.2]);
        new RecordMeasurementsHandler($this->measurementDays, $this->measures, $this->clock)(new RecordMeasurements(new MeasurementDayId($this->uuids->generate()), $this->alex, self::day('2026-09-20'), ['waist' => 70.0]));
        $this->add('2026-06-01');
        $this->add('2026-09-28', Pose::Front);
        $this->add('2026-09-28', Pose::Back);
        $this->add('2026-09-28', userId: $this->sam);

        $sessions = new ListPhotoSessionsHandler($this->photos, $this->media, $this->weighIns, $this->measurementDays, $this->measures)(new ListPhotoSessions($this->alex));

        self::assertSame(['2026-09-28', '2026-06-01'], array_map(static fn ($session): string => $session->day->format('Y-m-d'), $sessions));
        self::assertSame([Pose::Front, Pose::Back], array_map(static fn (PhotoView $photo): Pose => $photo->pose, $sessions[0]->photos));
        self::assertSame(['2026-09-27', 51.0], [$sessions[0]->weight?->day->format('Y-m-d'), $sessions[0]->weight?->kg], 'the weigh-in before the day');
        self::assertSame(['2026-09-20', ['waist' => 70.0]], [$sessions[0]->measurements?->day->format('Y-m-d'), $sessions[0]->measurements?->centimetres]);
        self::assertSame([45.0, null], [$sessions[1]->weight?->kg, $sessions[1]->measurements]);
    }

    public function testRemovingAPhotoDeletesItsFileAndOnlyTheOwnerCan(): void
    {
        $id = $this->add('2026-09-29');
        $remove = new RemoveProgressPhotoHandler($this->photos, $this->events, $this->clock);
        $deleteFile = new DeleteRemovedPhotoFile($this->media);

        try {
            $remove(new RemoveProgressPhoto($this->sam, new ProgressPhotoId($id)));
            self::fail('someone else\'s photo');
        } catch (ProgressPhotoNotFound) {
            self::assertCount(1, $this->media->stored);
        }

        $remove(new RemoveProgressPhoto($this->alex, new ProgressPhotoId($id)));
        self::assertSame([], $this->photos->photos);
        self::assertCount(1, $this->media->stored, 'the file waits for the removal to commit');

        $removed = $this->events->published[0] ?? null;
        self::assertInstanceOf(ProgressPhotoRemoved::class, $removed);
        $deleteFile($removed);
        self::assertSame([], $this->media->stored);
    }
}

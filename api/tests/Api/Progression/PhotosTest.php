<?php

declare(strict_types=1);

namespace App\Tests\Api\Progression;

use App\Tests\Support\Api\ProgressionApiTestCase;
use App\Tests\Support\Media\Images;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class PhotosTest extends ProgressionApiTestCase
{
    protected function tearDown(): void
    {
        Images::clean();
        // The database is rolled back after each test; the files written meanwhile go too.
        $files = glob(self::getContainer()->getParameter('kernel.project_dir').'/var/media/test/*.webp');
        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }
        parent::tearDown();
    }

    private function upload(string $path, string $day, string $pose = 'front'): ResponseInterface
    {
        return $this->client->request('POST', '/api/progression/photos', [
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'multipart/form-data'],
            'extra' => ['parameters' => ['day' => $day, 'pose' => $pose], 'files' => ['file' => new UploadedFile($path, 'IMG_2041.jpg', 'image/jpeg', null, true)]],
        ]);
    }

    public function testAPhotoIsKeptPrivateAndServedThroughALinkThatExpires(): void
    {
        $this->register();
        $photo = $this->upload(Images::jpeg(3000, 2000, gps: true), $this->daysAgo(0), 'side')->toArray();

        self::assertResponseStatusCodeSame(201);
        self::assertSame([$this->daysAgo(0), 'side', 2048, 1365], [$photo['day'], $photo['pose'], $photo['width'], $photo['height']]);

        $file = $this->call('GET', self::string($photo, 'url'));
        self::assertResponseStatusCodeSame(200);
        self::assertSame('image/webp', $file->getHeaders()['content-type'][0] ?? null);
        self::assertStringContainsString('no-store', $file->getHeaders()['cache-control'][0] ?? '');
        self::assertStringNotContainsString('Exif', $file->getContent());

        $download = $this->call('GET', self::string($photo, 'downloadUrl'));
        self::assertStringContainsString('attachment; filename=photo-'.$this->daysAgo(0).'-side.webp', $download->getHeaders()['content-disposition'][0] ?? '');
        $this->call('GET', str_replace('signature=', 'signature=0', self::string($photo, 'url')));
        self::assertResponseStatusCodeSame(404, 'a tampered link');
        self::mockTime('+301 seconds');
        $this->call('GET', self::string($photo, 'url'));
        self::assertResponseStatusCodeSame(404, 'an expired link');
    }

    public function testSessionsShowADaysPhotosWithItsWeight(): void
    {
        $this->register();
        $this->weigh($this->daysAgo(2), 51.2);
        $this->upload(Images::png(400, 600), $this->daysAgo(0));
        $this->upload(Images::png(400, 600), $this->daysAgo(0), 'back');
        $this->upload(Images::png(400, 600), $this->daysAgo(10));

        $sessions = $this->getJson('/api/progression/photo-sessions');

        self::assertSame([$this->daysAgo(0), $this->daysAgo(10)], array_column($sessions, 'day'));
        self::assertSame(['front', 'back'], array_column(self::array($sessions, '0.photos'), 'pose'));
        self::assertSame(['day' => $this->daysAgo(2), 'weightKg' => 51.2], self::array($sessions, '0.weight'));
        self::assertNull(self::at($sessions, '1.weight'));
        self::assertStringContainsString('-thumbnail.webp', self::string($sessions, '0.photos.0.thumbnailUrl'));
    }

    public function testWhatIsNotAPhotoOrADayIsRefused(): void
    {
        $this->register();

        $this->upload(Images::file('not an image', 'jpg'), $this->daysAgo(0));
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('media.invalid_photo');
        $this->upload(Images::png(20, 20), $this->today->modify('+2 days')->format('Y-m-d'));
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('progression.invalid_photo');
        $this->upload(Images::png(20, 20), $this->daysAgo(0), 'selfie');
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', '/api/progression/photos', ['headers' => ['Accept' => 'application/json', 'Content-Type' => 'multipart/form-data'], 'extra' => ['parameters' => ['day' => $this->daysAgo(0), 'pose' => 'front']]]);
        self::assertResponseStatusCodeSame(422, 'no file');
        self::assertSame([], $this->getJson('/api/progression/photo-sessions'));
    }

    public function testAPhotoIsTheOwnersOnlyAndDeletedForGood(): void
    {
        $this->register();
        $photo = $this->upload(Images::png(40, 40), $this->daysAgo(0))->toArray();
        $id = self::string($photo, 'id');

        $this->register('sam@example.com', 'Sam');
        $this->call('GET', '/api/progression/photos/'.$id);
        self::assertResponseStatusCodeSame(404);
        self::assertProblemCode('progression.photo_not_found');
        $this->call('DELETE', '/api/progression/photos/'.$id);
        self::assertResponseStatusCodeSame(404);
        self::assertSame([], $this->getJson('/api/progression/photo-sessions'));

        $this->signIn();
        $fresh = $this->getJson('/api/progression/photos/'.$id);
        $this->call('DELETE', '/api/progression/photos/'.$id);
        self::assertResponseStatusCodeSame(204);
        $this->call('GET', self::string($fresh, 'url'));
        self::assertResponseStatusCodeSame(404, 'the file is gone too');
        $this->call('GET', '/api/progression/photos/'.$id);
        self::assertResponseStatusCodeSame(404);
    }
}

<?php

declare(strict_types=1);

namespace App\System\Infrastructure\Backup;

use App\System\Application\Backup\BackupFailed;
use App\System\Application\Backup\BackupStore;
use AsyncAws\Core\Exception\Exception as AwsException;
use AsyncAws\S3\S3Client;

/**
 * A private S3-compatible bucket of its own (not the photos'), streamed both ways: a dump never
 * sits whole in memory.
 */
final readonly class S3BackupStore implements BackupStore
{
    public function __construct(private S3Client $client, private string $bucket)
    {
    }

    public function put(string $key, string $path): void
    {
        $body = @fopen($path, 'r');
        if (false === $body) {
            throw BackupFailed::because('the encrypted backup could not be read');
        }
        try {
            $this->client->putObject(['Bucket' => $this->bucket, 'Key' => $key, 'Body' => $body, 'ContentType' => 'application/octet-stream'])->resolve();
        } catch (AwsException $failure) {
            throw BackupFailed::because(\sprintf('the backup could not be sent to the bucket "%s": %s', $this->bucket, $failure->getMessage()), $failure);
        } finally {
            fclose($body);
        }
    }

    public function keys(string $prefix): array
    {
        try {
            $keys = [];
            foreach ($this->client->listObjectsV2(['Bucket' => $this->bucket, 'Prefix' => $prefix])->getContents() as $object) {
                $key = $object->getKey();
                if (null !== $key) {
                    $keys[] = $key;
                }
            }

            return $keys;
        } catch (AwsException $failure) {
            throw BackupFailed::because(\sprintf('the bucket "%s" could not be listed: %s', $this->bucket, $failure->getMessage()), $failure);
        }
    }

    public function fetch(string $key, string $path): void
    {
        $out = @fopen($path, 'w');
        if (false === $out) {
            throw BackupFailed::because('the backup could not be written locally');
        }
        try {
            foreach ($this->client->getObject(['Bucket' => $this->bucket, 'Key' => $key])->getBody()->getChunks() as $chunk) {
                fwrite($out, $chunk);
            }
        } catch (AwsException $failure) {
            throw BackupFailed::because(\sprintf('the backup %s could not be fetched: %s', $key, $failure->getMessage()), $failure);
        } finally {
            fclose($out);
        }
    }

    public function delete(string $key): void
    {
        try {
            $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $key])->resolve();
        } catch (AwsException $failure) {
            throw BackupFailed::because(\sprintf('the backup %s could not be deleted: %s', $key, $failure->getMessage()), $failure);
        }
    }
}

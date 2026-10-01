<?php

declare(strict_types=1);

namespace App\System\Infrastructure\Backup;

use App\Shared\Infrastructure\Storage\S3Clients;
use App\System\Application\Backup\BackupStore;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The backup bucket from BACKUP_S3_* (services.yaml builds BackupStore with it): its own
 * credentials, so that it may live with another provider than the photos, and a leak of one does
 * not open the other.
 */
final readonly class BackupStoreFactory
{
    public function __construct(
        #[Autowire('%env(BACKUP_S3_ENDPOINT)%')]
        private string $endpoint,
        #[Autowire('%env(BACKUP_S3_REGION)%')]
        private string $region,
        #[Autowire('%env(BACKUP_S3_BUCKET)%')]
        private string $bucket,
        #[Autowire('%env(BACKUP_S3_ACCESS_KEY)%')]
        private string $accessKey,
        #[Autowire('%env(BACKUP_S3_SECRET_KEY)%')]
        private string $secretKey,
        #[Autowire('%env(bool:BACKUP_S3_PATH_STYLE)%')]
        private bool $pathStyle,
    ) {
    }

    public function create(): BackupStore
    {
        if ('' === $this->bucket) {
            return new UnconfiguredBackupStore();
        }

        return new S3BackupStore(S3Clients::make($this->endpoint, $this->region, $this->accessKey, $this->secretKey, $this->pathStyle), $this->bucket);
    }
}

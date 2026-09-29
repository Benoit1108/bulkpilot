<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Storage;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Links to files on the local disk (development and tests), signed like presigned S3 links: the
 * key, the expiry and the download name, with a key derived from the application secret.
 */
final readonly class SignedLocalUrls
{
    public const string PATH = '/api/media/';

    private string $key;

    public function __construct(#[Autowire('%kernel.secret%')] string $secret)
    {
        $this->key = hash_hkdf('sha256', $secret, info: 'media-links');
    }

    public function sign(string $fileKey, int $expires, string $download): string
    {
        return hash_hmac('sha256', $fileKey."\n".$expires."\n".$download, $this->key);
    }

    public function url(string $fileKey, \DateTimeImmutable $expiresAt, ?string $download): string
    {
        $expires = $expiresAt->getTimestamp();
        $query = ['expires' => $expires, 'signature' => $this->sign($fileKey, $expires, $download ?? '')];
        if (null !== $download) {
            $query['download'] = $download;
        }

        return self::PATH.rawurlencode($fileKey).'?'.http_build_query($query);
    }

    public function verifies(string $fileKey, int $expires, string $download, string $signature, \DateTimeImmutable $now): bool
    {
        return $expires >= $now->getTimestamp() && hash_equals($this->sign($fileKey, $expires, $download), $signature);
    }
}

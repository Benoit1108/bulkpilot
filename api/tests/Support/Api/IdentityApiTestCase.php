<?php

declare(strict_types=1);

namespace App\Tests\Support\Api;

use ApiPlatform\Test\ApiTestCase;
use ApiPlatform\Test\Client;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * One HTTP client, several "devices": each device keeps its own cookie jar (session).
 */
abstract class IdentityApiTestCase extends ApiTestCase
{
    protected const string PASSWORD = 'correct horse battery';

    protected Client $client;

    /** @var array<string, list<Cookie>> */
    private array $devices = [];
    private string $device = 'laptop';

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Rate limiter state lives in the (persistent) cache: never carry it from one test to the next.
        self::getContainer()->get('cache.rate_limiter')->clear();
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string>     $headers
     */
    protected function call(string $method, string $uri, ?array $json = null, array $headers = []): ResponseInterface
    {
        $options = ['headers' => ['Accept' => 'application/json', 'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/140.0'] + $headers];
        if (null !== $json) {
            $options['json'] = $json;
        }

        return $this->client->request($method, $uri, $options);
    }

    protected function useDevice(string $name): void
    {
        $jar = $this->client->getCookieJar();
        $this->devices[$this->device] = array_values($jar->all());
        $jar->clear();
        foreach ($this->devices[$name] ?? [] as $cookie) {
            $jar->set($cookie);
        }
        $this->device = $name;
    }

    protected function register(string $email = 'alex@example.com', string $firstName = 'Alex'): string
    {
        $response = $this->call('POST', '/api/auth/register', ['firstName' => $firstName, 'email' => $email, 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(201);

        $id = $response->toArray()['id'];
        \assert(\is_string($id));

        return $id;
    }

    protected function signIn(string $email = 'alex@example.com', string $password = self::PASSWORD): ResponseInterface
    {
        return $this->call('POST', '/api/auth/sign-in', ['email' => $email, 'password' => $password]);
    }

    /**
     * @return list<array{id: string, deviceLabel: string, current: bool}>
     */
    protected function listSessions(): array
    {
        $sessions = [];
        foreach ($this->call('GET', '/api/me/sessions')->toArray() as $session) {
            self::assertIsArray($session);
            self::assertIsString($session['id']);
            self::assertIsString($session['deviceLabel']);
            self::assertIsBool($session['current']);
            $sessions[] = ['id' => $session['id'], 'deviceLabel' => $session['deviceLabel'], 'current' => $session['current']];
        }

        return $sessions;
    }

    /**
     * Token from the link of the last email sent during the previous request.
     */
    protected function tokenFromLastEmail(): string
    {
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertSame(1, preg_match('/\?token=([A-Za-z0-9._%-]+)/', (string) $message->getTextBody(), $matches));

        return rawurldecode($matches[1]);
    }

    protected function registerVerified(string $email = 'alex@example.com'): string
    {
        $id = $this->register($email);
        $this->call('POST', '/api/auth/email-verification', ['token' => $this->tokenFromLastEmail()]);
        self::assertResponseStatusCodeSame(204);

        return $id;
    }

    protected static function assertProblemCode(string $code): void
    {
        self::assertJsonContains(['code' => $code]);
    }
}

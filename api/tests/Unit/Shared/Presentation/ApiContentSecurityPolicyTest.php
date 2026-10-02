<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Presentation;

use App\Shared\Presentation\Http\ApiContentSecurityPolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ApiContentSecurityPolicyTest extends TestCase
{
    public function testEveryApiResponseGetsThePolicyHtmlErrorPagesIncluded(): void
    {
        self::assertSame(ApiContentSecurityPolicy::POLICY, $this->policyOf('/api/nope', new Response('<html></html>', 404, ['Content-Type' => 'text/html']), debug: false));
        self::assertSame(ApiContentSecurityPolicy::POLICY, $this->policyOf('/api/health', new Response('{}', 200, ['Content-Type' => 'application/json']), debug: false));
    }

    public function testOnlyTheDocsWithDebugOnAreLeftAlone(): void
    {
        self::assertNull($this->policyOf('/api/docs', new Response('<html></html>'), debug: true));
        self::assertSame(ApiContentSecurityPolicy::POLICY, $this->policyOf('/api/docs', new Response('<html></html>'), debug: false));
        self::assertSame(ApiContentSecurityPolicy::POLICY, $this->policyOf('/api/workouts', new Response('<html></html>', 200, ['Content-Type' => 'text/html']), debug: true));
    }

    public function testThePagesOfTheAppAndAnExistingPolicyAreLeftAlone(): void
    {
        self::assertNull($this->policyOf('/nutrition', new Response('<html></html>'), debug: false));
        self::assertSame("default-src 'self'", $this->policyOf('/api/media/x', new Response('', 200, ['Content-Security-Policy' => "default-src 'self'"]), debug: false));
    }

    private function policyOf(string $path, Response $response, bool $debug): ?string
    {
        $kernel = self::createStub(HttpKernelInterface::class);
        (new ApiContentSecurityPolicy($debug))(new ResponseEvent($kernel, Request::create($path), HttpKernelInterface::MAIN_REQUEST, $response));

        return $response->headers->get('Content-Security-Policy');
    }
}

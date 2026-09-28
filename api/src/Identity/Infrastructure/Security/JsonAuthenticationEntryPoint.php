<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Anonymous access to a protected endpoint: 401 problem, which the PWA turns into a redirect to sign-in.
 */
final readonly class JsonAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(
            [
                'type' => '/errors/shared.not_authenticated',
                'title' => 'Unauthorized',
                'status' => Response::HTTP_UNAUTHORIZED,
                'detail' => 'Authentication is required.',
                'code' => 'shared.not_authenticated',
            ],
            Response::HTTP_UNAUTHORIZED,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}

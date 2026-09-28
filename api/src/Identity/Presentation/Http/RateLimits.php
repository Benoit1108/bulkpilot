<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Throttles abuse-prone endpoints (config/packages/framework.yaml → rate_limiter).
 * Factories are autowired by argument name ($<limiter>Limiter).
 */
final readonly class RateLimits
{
    public function __construct(
        private RequestStack $requestStack,
        private RateLimiterFactoryInterface $signInLimiter,
        private RateLimiterFactoryInterface $signInIpLimiter,
        private RateLimiterFactoryInterface $signInAccountLimiter,
        private RateLimiterFactoryInterface $registrationLimiter,
        private RateLimiterFactoryInterface $emailLinksLimiter,
        private RateLimiterFactoryInterface $secondFactorLimiter,
        private RateLimiterFactoryInterface $passwordConfirmLimiter,
    ) {
    }

    /**
     * Keyed by the client's IP address.
     *
     * @param 'sign_in_ip'|'registration'|'email_links' $limiter
     */
    public function consumeForClient(string $limiter): void
    {
        $this->consume($limiter, 'ip|'.$this->clientIp());
    }

    /**
     * Keyed by the client's IP address and a subject (an email address).
     *
     * @param 'sign_in'|'email_links' $limiter
     */
    public function consumeForClientAnd(string $limiter, string $subject): void
    {
        $this->consume($limiter, 'ip|'.$this->clientIp().'|'.mb_strtolower(trim($subject)));
    }

    /**
     * Keyed by an account (user id or email), whatever the IP or session.
     *
     * @param 'sign_in_account'|'email_links'|'second_factor'|'password_confirm' $limiter
     */
    public function consumeForAccount(string $limiter, string $account): void
    {
        $this->consume($limiter, 'account|'.mb_strtolower(trim($account)));
    }

    private function consume(string $limiter, string $key): void
    {
        $factory = match ($limiter) {
            'sign_in' => $this->signInLimiter,
            'sign_in_ip' => $this->signInIpLimiter,
            'sign_in_account' => $this->signInAccountLimiter,
            'registration' => $this->registrationLimiter,
            'email_links' => $this->emailLinksLimiter,
            'second_factor' => $this->secondFactorLimiter,
            'password_confirm' => $this->passwordConfirmLimiter,
            default => throw new \InvalidArgumentException('Unknown rate limiter '.$limiter),
        };

        $limit = $factory->create(hash('sha256', $limiter.'|'.$key))->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()), 'Too many attempts, retry later.');
        }
    }

    private function clientIp(): string
    {
        $ip = $this->requestStack->getMainRequest()?->getClientIp();
        if (null === $ip) {
            // Only reachable outside HTTP: never share one bucket between unrelated callers.
            throw new \LogicException('Rate limiting by client requires an HTTP request.');
        }

        return $ip;
    }
}

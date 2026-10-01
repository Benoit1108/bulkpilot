<?php

declare(strict_types=1);

namespace App;

use App\Identity\Application\Port\ExternalIdentityProvider;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\DomainEventSubscriber;
use App\Shared\Application\Bus\Job\JobHandler;
use App\Shared\Application\Bus\Query\QueryHandler;
use App\System\Application\Health\HealthCheck;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Application and Domain code carries no framework attribute: use cases are recognised
     * through marker interfaces and bound to their Messenger bus here, for every service
     * definition file (including test-only ones).
     */
    protected function build(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(CommandHandler::class)
            ->addTag('messenger.message_handler', ['bus' => 'command.bus']);
        $container->registerForAutoconfiguration(JobHandler::class)
            ->addTag('messenger.message_handler', ['bus' => 'job.bus']);
        $container->registerForAutoconfiguration(QueryHandler::class)
            ->addTag('messenger.message_handler', ['bus' => 'query.bus']);
        $container->registerForAutoconfiguration(DomainEventSubscriber::class)
            ->addTag('messenger.message_handler', ['bus' => 'event.bus']);
        $container->registerForAutoconfiguration(HealthCheck::class)
            ->addTag('app.health_check');
        $container->registerForAutoconfiguration(ExternalIdentityProvider::class)
            ->addTag('app.identity_provider');
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}

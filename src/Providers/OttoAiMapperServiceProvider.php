<?php

namespace OttoAiMapper\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Modules\Catalog\Events\CatalogExportInitiated;
use OttoAiMapper\Cron\AutoMappingCron;

/**
 * Class OttoAiMapperServiceProvider
 *
 * Main service provider for the OttoAiMapper plugin.
 * Registers routes, events, and scheduled cron jobs.
 *
 * @package OttoAiMapper\Providers
 */
class OttoAiMapperServiceProvider extends ServiceProvider
{
    /**
     * Register services and bind contracts into the container.
     */
    public function register(): void
    {
        $this->getApplication()->register(OttoAiMapperRouteServiceProvider::class);

        // Bind the AI mapping service as a singleton
        $this->getApplication()->singleton(
            \OttoAiMapper\Contracts\AiMappingServiceContract::class,
            \OttoAiMapper\Services\OpenAiMappingService::class
        );
    }

    /**
     * Boot the plugin: wire up events and crons.
     */
    public function boot(Dispatcher $eventDispatcher): void
    {
        // Listen on catalog export initiation to trigger AI mapping if auto-apply is enabled
        $eventDispatcher->listen(
            CatalogExportInitiated::class,
            function (CatalogExportInitiated $event) {
                /** @var \OttoAiMapper\Services\MappingOrchestratorService $orchestrator */
                $orchestrator = pluginApp(\OttoAiMapper\Services\MappingOrchestratorService::class);
                $orchestrator->handleCatalogExportInitiated($event);
            }
        );
    }
}

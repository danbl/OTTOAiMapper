<?php

namespace OttoAiMapper\Cron;

use OttoAiMapper\Services\MappingOrchestratorService;
use Plenty\Modules\Cron\Contracts\CronHandler;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;

/**
 * Class AutoMappingCron
 *
 * Runs daily to re-trigger AI mapping for the configured OTTO catalog.
 * Register in ServiceProvider via $this->getApplication()->bind(CronHandler::class, static::class)
 * or via the scheduler in PlentyONE.
 *
 * @package OttoAiMapper\Cron
 */
class AutoMappingCron extends CronHandler
{
    use Loggable;

    /** @var MappingOrchestratorService */
    private MappingOrchestratorService $orchestrator;

    /** @var ConfigRepository */
    private ConfigRepository $config;

    public function __construct(MappingOrchestratorService $orchestrator, ConfigRepository $config)
    {
        $this->orchestrator = $orchestrator;
        $this->config       = $config;
    }

    /**
     * Entry point called by the PlentyONE cron scheduler.
     */
    public function handle(): void
    {
        $autoApply = $this->config->get('OttoAiMapper.auto_apply', '0');
        if ($autoApply !== '1') {
            return;
        }

        $catalogId = (int) $this->config->get('OttoAiMapper.otto_catalog_id', '0');
        if ($catalogId <= 0) {
            $this->getLogger('OttoAiMapper')->warning('AutoMappingCron: No catalog ID configured.');
            return;
        }

        try {
            $result = $this->orchestrator->runMapping($catalogId);
            $this->getLogger('OttoAiMapper')->info('AutoMappingCron completed', [
                'catalogId'   => $catalogId,
                'mappedCount' => $result['mappedCount'],
                'total'       => $result['totalFields'],
            ]);
        } catch (\Throwable $e) {
            $this->getLogger('OttoAiMapper')->error('AutoMappingCron failed', [
                'catalogId' => $catalogId,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}

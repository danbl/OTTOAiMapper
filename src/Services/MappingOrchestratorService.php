<?php

namespace OttoAiMapper\Services;

use OttoAiMapper\Contracts\AiMappingServiceContract;
use OttoAiMapper\Services\CatalogFieldService;
use Plenty\Modules\Catalog\Contracts\CatalogRepositoryContract;
use Plenty\Modules\Catalog\Contracts\CatalogTemplateRepositoryContract;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;

/**
 * Class MappingOrchestratorService
 *
 * Coordinates the full AI mapping workflow:
 *   1. Fetch OTTO catalog template fields
 *   2. Fetch PlentyONE source fields
 *   3. Call the AI mapping service
 *   4. Persist the result
 *   5. Optionally auto-apply the mapping to the catalog
 *
 * @package OttoAiMapper\Services
 */
class MappingOrchestratorService
{
    use Loggable;

    /** @var AiMappingServiceContract */
    private AiMappingServiceContract $aiService;

    /** @var CatalogRepositoryContract */
    private CatalogRepositoryContract $catalogRepo;

    /** @var CatalogTemplateRepositoryContract */
    private CatalogTemplateRepositoryContract $templateRepo;

    /** @var ConfigRepository */
    private ConfigRepository $config;

    /** @var MappingStorageService */
    private MappingStorageService $storageService;

    /** @var CatalogFieldService */
    private CatalogFieldService $fieldService;

    public function __construct(
        AiMappingServiceContract            $aiService,
        CatalogRepositoryContract           $catalogRepo,
        CatalogTemplateRepositoryContract   $templateRepo,
        ConfigRepository                    $config,
        MappingStorageService               $storageService,
        CatalogFieldService                 $fieldService
    ) {
        $this->aiService      = $aiService;
        $this->catalogRepo    = $catalogRepo;
        $this->templateRepo   = $templateRepo;
        $this->config         = $config;
        $this->storageService = $storageService;
        $this->fieldService   = $fieldService;
    }

    /**
     * Run AI mapping for a given catalog.
     *
     * @param  int    $catalogId  The PlentyONE catalog ID
     * @return array              The mapping result including proposals and metadata
     */
    public function runMapping(int $catalogId): array
    {
        $this->getLogger('OttoAiMapper')->info('Starting AI mapping', ['catalogId' => $catalogId]);

        // 1. Load the catalog and its template
        $catalog = $this->catalogRepo->get($catalogId);
        if (!$catalog) {
            throw new \InvalidArgumentException("Catalog with ID {$catalogId} not found.");
        }

        $templateId = $catalog->templateId ?? null;
        $template   = $templateId ? $this->templateRepo->get($templateId) : null;

        // 2. Build OTTO field list from the catalog template
        $ottoFields = $this->extractOttoFields($template);

        // 3. Build PlentyONE source field list
        $plentyFields = $this->fieldService->getPlentyFields();

        // 4. Call AI mapping
        $language  = $this->config->get('OttoAiMapper.language', 'de');
        $proposals = $this->aiService->generateMapping($ottoFields, $plentyFields, $language);

        // 5. Persist result
        $result = [
            'catalogId'   => $catalogId,
            'templateId'  => $templateId,
            'mappedAt'    => date('c'),
            'proposals'   => $proposals,
            'totalFields' => count($ottoFields),
            'mappedCount' => count(array_filter($proposals, fn($p) => $p['plenty_field'] !== null)),
        ];

        $this->storageService->saveResult($catalogId, $result);

        // 6. Auto-apply if configured
        if ($this->config->get('OttoAiMapper.auto_apply', '0') === '1') {
            $this->applyMapping($catalogId, $proposals);
            $result['auto_applied'] = true;
        }

        $this->getLogger('OttoAiMapper')->info('AI mapping completed', [
            'catalogId'   => $catalogId,
            'mappedCount' => $result['mappedCount'],
            'total'       => $result['totalFields'],
        ]);

        return $result;
    }

    /**
     * Apply approved proposals to the actual catalog mappings.
     *
     * @param  int   $catalogId
     * @param  array $proposals  The proposals to apply (filtered by user or auto)
     */
    public function applyMapping(int $catalogId, array $proposals): void
    {
        $toApply = array_filter($proposals, fn($p) => $p['plenty_field'] !== null && $p['above_threshold']);

        foreach ($toApply as $proposal) {
            try {
                // Use the PlentyONE catalog mapping API to set the field mapping
                $this->catalogRepo->updateMapping($catalogId, [
                    'field'       => $proposal['otto_field'],
                    'mappedField' => $proposal['plenty_field'],
                ]);
            } catch (\Throwable $e) {
                $this->getLogger('OttoAiMapper')->error('Failed to apply mapping', [
                    'catalogId'  => $catalogId,
                    'ottoField'  => $proposal['otto_field'],
                    'exception'  => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle the CatalogExportInitiated event for auto-triggering.
     */
    public function handleCatalogExportInitiated($event): void
    {
        $autoApply = $this->config->get('OttoAiMapper.auto_apply', '0');
        if ($autoApply !== '1') {
            return;
        }

        try {
            $catalogId = $event->getCatalogId();
            // Only run if no recent mapping exists (within last 24 hours)
            $existing = $this->storageService->loadResult($catalogId);
            if ($existing && isset($existing['mappedAt'])) {
                $age = time() - strtotime($existing['mappedAt']);
                if ($age < 86400) {
                    return; // Skip – mapping is fresh enough
                }
            }
            $this->runMapping($catalogId);
        } catch (\Throwable $e) {
            $this->getLogger('OttoAiMapper')->error('Auto-mapping on export failed', ['exception' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Extract OTTO Market field definitions from a catalog template.
     */
    private function extractOttoFields(?object $template): array
    {
        if (!$template) {
            return $this->fieldService->getOttoFields();
        }

        $fields = [];
        foreach ($template->getMappings() ?? [] as $mapping) {
            $fields[] = [
                'key'         => $mapping->getKey(),
                'label'       => $mapping->getLabel(),
                'description' => $mapping->getDescription() ?? '',
                'required'    => $mapping->isRequired(),
                'type'        => $mapping->getFieldType() ?? 'string',
            ];
        }

        return !empty($fields) ? $fields : $this->fieldService->getOttoFields();
    }
}

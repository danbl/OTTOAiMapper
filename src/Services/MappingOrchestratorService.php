<?php

namespace OttoAiMapper\Services;

use OttoAiMapper\Contracts\AiMappingServiceContract;
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

    public function __construct(
        AiMappingServiceContract            $aiService,
        CatalogRepositoryContract           $catalogRepo,
        CatalogTemplateRepositoryContract   $templateRepo,
        ConfigRepository                    $config,
        MappingStorageService               $storageService
    ) {
        $this->aiService      = $aiService;
        $this->catalogRepo    = $catalogRepo;
        $this->templateRepo   = $templateRepo;
        $this->config         = $config;
        $this->storageService = $storageService;
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
        $plentyFields = $this->buildPlentyFieldList();

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
            // Fallback: return a standard OTTO Market field set
            return $this->getDefaultOttoFields();
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

        return !empty($fields) ? $fields : $this->getDefaultOttoFields();
    }

    /**
     * Default OTTO Market fields (fallback / standalone mode).
     */
    private function getDefaultOttoFields(): array
    {
        return [
            ['key' => 'sku',                  'label' => 'SKU',                       'description' => 'Unique seller article number',           'required' => true,  'type' => 'string'],
            ['key' => 'productTitle',         'label' => 'Product Title',             'description' => 'Product name shown on OTTO',             'required' => true,  'type' => 'string'],
            ['key' => 'brandName',            'label' => 'Brand',                     'description' => 'Manufacturer brand name',                'required' => true,  'type' => 'string'],
            ['key' => 'description',          'label' => 'Description',               'description' => 'Full product description',               'required' => true,  'type' => 'text'],
            ['key' => 'ean',                  'label' => 'EAN / GTIN',               'description' => 'European Article Number',                'required' => true,  'type' => 'string'],
            ['key' => 'categoryPath',         'label' => 'Category',                  'description' => 'OTTO category path',                     'required' => true,  'type' => 'string'],
            ['key' => 'retailPrice',          'label' => 'Retail Price',              'description' => 'Selling price in EUR',                   'required' => true,  'type' => 'decimal'],
            ['key' => 'msrp',                 'label' => 'Recommended Retail Price',  'description' => 'Manufacturer suggested retail price',    'required' => false, 'type' => 'decimal'],
            ['key' => 'stockQuantity',        'label' => 'Stock Quantity',            'description' => 'Available stock',                        'required' => true,  'type' => 'integer'],
            ['key' => 'mainImageUrl',         'label' => 'Main Image URL',            'description' => 'URL to the main product image',          'required' => true,  'type' => 'url'],
            ['key' => 'additionalImageUrls',  'label' => 'Additional Image URLs',     'description' => 'Comma-separated additional image URLs',  'required' => false, 'type' => 'string'],
            ['key' => 'color',                'label' => 'Color',                     'description' => 'Product color',                          'required' => false, 'type' => 'string'],
            ['key' => 'size',                 'label' => 'Size',                      'description' => 'Product size (clothing/shoes)',          'required' => false, 'type' => 'string'],
            ['key' => 'weight',               'label' => 'Weight (kg)',               'description' => 'Product weight in kilograms',            'required' => false, 'type' => 'decimal'],
            ['key' => 'deliveryType',         'label' => 'Delivery Type',             'description' => 'Parcel, freight, etc.',                  'required' => true,  'type' => 'string'],
            ['key' => 'deliveryTime',         'label' => 'Delivery Time (days)',      'description' => 'Expected delivery time in business days','required' => false, 'type' => 'integer'],
            ['key' => 'materialComposition',  'label' => 'Material',                  'description' => 'Material composition in percent',        'required' => false, 'type' => 'string'],
            ['key' => 'countryOfOrigin',      'label' => 'Country of Origin',         'description' => 'ISO country code',                       'required' => false, 'type' => 'string'],
        ];
    }

    /**
     * Build the list of available PlentyONE source fields.
     * In a full implementation this would be fetched from the PlentyONE field provider.
     */
    private function buildPlentyFieldList(): array
    {
        return [
            // Item fields
            ['key' => 'item.id',                          'label' => 'Item ID',                    'type' => 'integer'],
            ['key' => 'item.manufacturer.name',           'label' => 'Manufacturer Name',          'type' => 'string'],
            ['key' => 'item.manufacturer.countryOfOrigin','label' => 'Country of Origin',          'type' => 'string'],
            ['key' => 'item.condition',                   'label' => 'Item Condition',             'type' => 'string'],
            ['key' => 'item.description.name',            'label' => 'Item Name',                  'type' => 'string'],
            ['key' => 'item.description.shortDescription','label' => 'Short Description',          'type' => 'text'],
            ['key' => 'item.description.description',     'label' => 'Long Description',           'type' => 'text'],
            ['key' => 'item.description.technicalData',   'label' => 'Technical Data',             'type' => 'text'],
            ['key' => 'item.category.branch',             'label' => 'Category Branch',            'type' => 'string'],

            // Variation fields
            ['key' => 'variation.id',                     'label' => 'Variation ID',               'type' => 'integer'],
            ['key' => 'variation.number',                 'label' => 'Variation Number (SKU)',     'type' => 'string'],
            ['key' => 'variation.externalId',             'label' => 'External Variation ID',      'type' => 'string'],
            ['key' => 'variation.mainWarehouseId',        'label' => 'Main Warehouse ID',          'type' => 'integer'],
            ['key' => 'variation.weightG',                'label' => 'Weight (g)',                 'type' => 'decimal'],
            ['key' => 'variation.widthMm',                'label' => 'Width (mm)',                 'type' => 'decimal'],
            ['key' => 'variation.heightMm',               'label' => 'Height (mm)',                'type' => 'decimal'],
            ['key' => 'variation.lengthMm',               'label' => 'Length (mm)',                'type' => 'decimal'],

            // Barcode / EAN
            ['key' => 'barcode.code',                     'label' => 'Barcode (EAN/GTIN)',         'type' => 'string'],
            ['key' => 'barcode.type',                     'label' => 'Barcode Type',               'type' => 'string'],

            // Pricing
            ['key' => 'price.salesPrice',                 'label' => 'Sales Price',                'type' => 'decimal'],
            ['key' => 'price.rrp',                        'label' => 'Recommended Retail Price',   'type' => 'decimal'],
            ['key' => 'price.netPrice',                   'label' => 'Net Price',                  'type' => 'decimal'],

            // Stock
            ['key' => 'stock.physical',                   'label' => 'Physical Stock',             'type' => 'integer'],
            ['key' => 'stock.net',                        'label' => 'Net Stock',                  'type' => 'integer'],

            // Images
            ['key' => 'image.urlPreview',                 'label' => 'Image URL (Preview)',        'type' => 'url'],
            ['key' => 'image.url',                        'label' => 'Image URL (Full)',           'type' => 'url'],
            ['key' => 'image.position',                   'label' => 'Image Position',             'type' => 'integer'],

            // Properties / Attributes
            ['key' => 'property.color',                   'label' => 'Property: Color',            'type' => 'string'],
            ['key' => 'property.size',                    'label' => 'Property: Size',             'type' => 'string'],
            ['key' => 'property.material',                'label' => 'Property: Material',         'type' => 'string'],
            ['key' => 'attribute.color.value',            'label' => 'Attribute: Color Value',     'type' => 'string'],
            ['key' => 'attribute.size.value',             'label' => 'Attribute: Size Value',      'type' => 'string'],

            // Shipping
            ['key' => 'shipping.profile.name',            'label' => 'Shipping Profile Name',      'type' => 'string'],
            ['key' => 'shipping.deliveryTime.min',        'label' => 'Min. Delivery Time (days)',  'type' => 'integer'],
            ['key' => 'shipping.deliveryTime.max',        'label' => 'Max. Delivery Time (days)',  'type' => 'integer'],
        ];
    }
}

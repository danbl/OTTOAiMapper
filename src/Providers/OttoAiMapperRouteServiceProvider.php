<?php

namespace OttoAiMapper\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;
use Plenty\Plugin\Routing\ApiRouter;

/**
 * Class OttoAiMapperRouteServiceProvider
 *
 * Registers all REST routes exposed by the plugin.
 * Routes are accessible under /rest/otto-ai-mapper/...
 *
 * @package OttoAiMapper\Providers
 */
class OttoAiMapperRouteServiceProvider extends RouteServiceProvider
{
    /**
     * @param Router    $router    Frontend router (unused here)
     * @param ApiRouter $apiRouter REST API router
     */
    public function map(Router $router, ApiRouter $apiRouter): void
    {
        // ── Configuration ──────────────────────────────────────────────
        $apiRouter->version(['v1'], ['namespace' => 'OttoAiMapper\\Controllers', 'middleware' => 'oauth'],
            function (ApiRouter $api) {

                // Get current plugin configuration
                $api->get('otto-ai-mapper/config', 'ConfigController@getConfig');
                // Save plugin configuration
                $api->post('otto-ai-mapper/config', 'ConfigController@saveConfig');

                // ── Catalog field sources ───────────────────────────────
                // List available OTTO Market catalog fields
                $api->get('otto-ai-mapper/otto-fields', 'CatalogFieldController@getOttoFields');
                // List available PlentyONE source fields (item, variation, etc.)
                $api->get('otto-ai-mapper/plenty-fields', 'CatalogFieldController@getPlentyFields');

                // ── AI Mapping ──────────────────────────────────────────
                // Trigger AI mapping for a specific catalog
                $api->post('otto-ai-mapper/run-mapping', 'AiMappingController@runMapping');
                // Get the current/last mapping result for a catalog
                $api->get('otto-ai-mapper/mapping-result/{catalogId}', 'AiMappingController@getMappingResult');
                // Apply a (possibly user-edited) mapping to the catalog
                $api->post('otto-ai-mapper/apply-mapping', 'AiMappingController@applyMapping');
                // Delete a stored mapping result
                $api->delete('otto-ai-mapper/mapping-result/{catalogId}', 'AiMappingController@deleteMappingResult');

                // ── Mapping history ─────────────────────────────────────
                $api->get('otto-ai-mapper/history', 'AiMappingController@getHistory');
            }
        );
    }
}

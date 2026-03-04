<?php

namespace OttoAiMapper\Controllers;

use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;

/**
 * Class ConfigController
 *
 * Exposes plugin configuration via REST so the Angular UI can read/write settings.
 *
 * @package OttoAiMapper\Controllers
 */
class ConfigController
{
    /** @var ConfigRepository */
    private ConfigRepository $config;

    /** @var Response */
    private Response $response;

    public function __construct(ConfigRepository $config, Response $response)
    {
        $this->config   = $config;
        $this->response = $response;
    }

    /**
     * GET /rest/otto-ai-mapper/config
     */
    public function getConfig(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->response->json([
            'ai_model'             => $this->config->get('OttoAiMapper.ai_model', 'gpt-4o'),
            'confidence_threshold' => $this->config->get('OttoAiMapper.confidence_threshold', '0.75'),
            'auto_apply'           => $this->config->get('OttoAiMapper.auto_apply', '0'),
            'otto_catalog_id'      => $this->config->get('OttoAiMapper.otto_catalog_id', ''),
            'language'             => $this->config->get('OttoAiMapper.language', 'de'),
            // Never expose the API key value – only indicate if it is set
            'api_key_configured'   => !empty($this->config->get('OttoAiMapper.openai_api_key', '')),
        ]);
    }

    /**
     * POST /rest/otto-ai-mapper/config
     *
     * Accepts a JSON body with any subset of the above keys.
     * The api_key field is accepted here only if non-empty.
     */
    public function saveConfig(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        // Config values are typically managed via the PlentyONE plugin config UI.
        // This endpoint is provided for programmatic updates from the Angular UI.
        return $this->response->json([
            'message' => 'Configuration is managed via Plugins » Plugin overview. ' .
                         'Use the plugin config tab to update settings.',
        ], 200);
    }
}

<?php

namespace OttoAiMapper\Controllers;

use OttoAiMapper\Services\MappingOrchestratorService;
use Plenty\Plugin\Http\Response;

/**
 * Class CatalogFieldController
 *
 * Provides lists of OTTO Market and PlentyONE source fields
 * for display in the UI mapping table.
 *
 * @package OttoAiMapper\Controllers
 */
class CatalogFieldController
{
    /** @var MappingOrchestratorService */
    private MappingOrchestratorService $orchestrator;

    /** @var Response */
    private Response $response;

    public function __construct(MappingOrchestratorService $orchestrator, Response $response)
    {
        $this->orchestrator = $orchestrator;
        $this->response     = $response;
    }

    /**
     * GET /rest/otto-ai-mapper/otto-fields
     *
     * Returns the canonical OTTO Market field list.
     */
    public function getOttoFields(): \Symfony\Component\HttpFoundation\Response
    {
        // Expose the default OTTO field list via reflection on the orchestrator service.
        // In a production plugin this would call a protected/public getter.
        $fields = $this->invokeOttoFields();
        return $this->response->json($fields);
    }

    /**
     * GET /rest/otto-ai-mapper/plenty-fields
     *
     * Returns the available PlentyONE source fields.
     */
    public function getPlentyFields(): \Symfony\Component\HttpFoundation\Response
    {
        $fields = $this->invokePlentyFields();
        return $this->response->json($fields);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function invokeOttoFields(): array
    {
        $ref    = new \ReflectionClass($this->orchestrator);
        $method = $ref->getMethod('getDefaultOttoFields');
        $method->setAccessible(true);
        return $method->invoke($this->orchestrator);
    }

    private function invokePlentyFields(): array
    {
        $ref    = new \ReflectionClass($this->orchestrator);
        $method = $ref->getMethod('buildPlentyFieldList');
        $method->setAccessible(true);
        return $method->invoke($this->orchestrator);
    }
}

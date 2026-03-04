<?php

namespace OttoAiMapper\Controllers;

use OttoAiMapper\Services\MappingOrchestratorService;
use OttoAiMapper\Services\MappingStorageService;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Log\Loggable;

/**
 * Class AiMappingController
 *
 * Handles all REST endpoints related to AI-powered field mapping.
 *
 * @package OttoAiMapper\Controllers
 */
class AiMappingController
{
    use Loggable;

    /** @var MappingOrchestratorService */
    private MappingOrchestratorService $orchestrator;

    /** @var MappingStorageService */
    private MappingStorageService $storage;

    /** @var Response */
    private Response $response;

    public function __construct(
        MappingOrchestratorService $orchestrator,
        MappingStorageService      $storage,
        Response                   $response
    ) {
        $this->orchestrator = $orchestrator;
        $this->storage      = $storage;
        $this->response     = $response;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /rest/otto-ai-mapper/run-mapping
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Trigger AI field mapping for a given catalog.
     *
     * Body: { "catalogId": 123 }
     */
    public function runMapping(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $catalogId = (int) $request->input('catalogId');

        if ($catalogId <= 0) {
            return $this->json(['error' => 'catalogId is required and must be a positive integer.'], 422);
        }

        try {
            $result = $this->orchestrator->runMapping($catalogId);
            return $this->json($result);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        } catch (\RuntimeException $e) {
            $this->getLogger('OttoAiMapper')->error('runMapping failed', ['exception' => $e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /rest/otto-ai-mapper/mapping-result/{catalogId}
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retrieve the stored mapping result for a catalog.
     */
    public function getMappingResult(int $catalogId): \Symfony\Component\HttpFoundation\Response
    {
        $result = $this->storage->loadResult($catalogId);

        if (!$result) {
            return $this->json(['error' => 'No mapping result found for catalog ' . $catalogId], 404);
        }

        return $this->json($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /rest/otto-ai-mapper/apply-mapping
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Apply (possibly user-edited) mapping proposals to the catalog.
     *
     * Body: { "catalogId": 123, "proposals": [...] }
     */
    public function applyMapping(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $catalogId = (int) $request->input('catalogId');
        $proposals = $request->input('proposals', []);

        if ($catalogId <= 0) {
            return $this->json(['error' => 'catalogId is required.'], 422);
        }

        if (!is_array($proposals) || empty($proposals)) {
            return $this->json(['error' => 'proposals must be a non-empty array.'], 422);
        }

        try {
            $this->orchestrator->applyMapping($catalogId, $proposals);
            return $this->json(['success' => true, 'appliedCount' => count(array_filter($proposals, fn($p) => $p['plenty_field'] !== null))]);
        } catch (\Throwable $e) {
            $this->getLogger('OttoAiMapper')->error('applyMapping failed', ['exception' => $e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE /rest/otto-ai-mapper/mapping-result/{catalogId}
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Delete the stored mapping result for a catalog.
     */
    public function deleteMappingResult(int $catalogId): \Symfony\Component\HttpFoundation\Response
    {
        $this->storage->deleteResult($catalogId);
        return $this->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /rest/otto-ai-mapper/history
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return the mapping run history (last 20 entries).
     */
    public function getHistory(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->json($this->storage->loadHistory());
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function json(mixed $data, int $status = 200): \Symfony\Component\HttpFoundation\Response
    {
        return $this->response->json($data, $status);
    }
}

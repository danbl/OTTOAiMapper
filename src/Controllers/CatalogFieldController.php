<?php

namespace OttoAiMapper\Controllers;

use OttoAiMapper\Services\CatalogFieldService;
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
    /** @var CatalogFieldService */
    private CatalogFieldService $fieldService;

    /** @var Response */
    private Response $response;

    public function __construct(CatalogFieldService $fieldService, Response $response)
    {
        $this->fieldService = $fieldService;
        $this->response     = $response;
    }

    /**
     * GET /rest/otto-ai-mapper/otto-fields
     */
    public function getOttoFields(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->response->json($this->fieldService->getOttoFields());
    }

    /**
     * GET /rest/otto-ai-mapper/plenty-fields
     */
    public function getPlentyFields(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->response->json($this->fieldService->getPlentyFields());
    }
}

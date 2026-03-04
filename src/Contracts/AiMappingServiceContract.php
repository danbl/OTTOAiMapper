<?php

namespace OttoAiMapper\Contracts;

/**
 * Interface AiMappingServiceContract
 *
 * Defines the contract for any AI mapping service implementation.
 * Swap out OpenAI for any other LLM by providing a new implementation.
 *
 * @package OttoAiMapper\Contracts
 */
interface AiMappingServiceContract
{
    /**
     * Generate field mappings using AI.
     *
     * @param  array  $ottoFields    List of OTTO target field definitions
     *                               [['key'=>'...', 'label'=>'...', 'description'=>'...', 'required'=>bool], ...]
     * @param  array  $plentyFields  List of PlentyONE source field definitions
     *                               [['key'=>'...', 'label'=>'...', 'type'=>'...'], ...]
     * @param  string $language      ISO language code ('de' | 'en')
     * @return array                 Mapping proposals
     *                               [['otto_field'=>'...', 'plenty_field'=>'...', 'confidence'=>0.0-1.0, 'reason'=>'...'], ...]
     */
    public function generateMapping(array $ottoFields, array $plentyFields, string $language = 'de'): array;
}

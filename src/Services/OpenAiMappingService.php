<?php

namespace OttoAiMapper\Services;

use OttoAiMapper\Contracts\AiMappingServiceContract;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;

/**
 * Class OpenAiMappingService
 *
 * Calls the OpenAI Chat Completions API to perform semantic field
 * matching between OTTO Market fields and PlentyONE source fields.
 *
 * @package OttoAiMapper\Services
 */
class OpenAiMappingService implements AiMappingServiceContract
{
    use Loggable;

    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';

    /** @var ConfigRepository */
    private ConfigRepository $config;

    public function __construct(ConfigRepository $config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function generateMapping(array $ottoFields, array $plentyFields, string $language = 'de'): array
    {
        $apiKey = $this->config->get('OttoAiMapper.openai_api_key', '');
        $model  = $this->config->get('OttoAiMapper.ai_model', 'gpt-4o');

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI API key is not configured. Please set it in the plugin configuration.');
        }

        $systemPrompt = $this->buildSystemPrompt($language);
        $userPrompt   = $this->buildUserPrompt($ottoFields, $plentyFields, $language);

        $payload = [
            'model'       => $model,
            'temperature' => 0.1, // Low temperature for deterministic, precise mapping
            'response_format' => ['type' => 'json_object'],
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
        ];

        $response = $this->callOpenAi($apiKey, $payload);

        return $this->parseResponse($response, $ottoFields);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build the AI system instruction prompt.
     */
    private function buildSystemPrompt(string $language): string
    {
        $langNote = $language === 'de'
            ? 'Antworte auf Deutsch wo Freitext verwendet wird.'
            : 'Respond in English where free text is used.';

        return <<<PROMPT
You are an expert e-commerce data architect specialising in marketplace integrations.
Your task is to semantically match OTTO Market target fields to PlentyONE source fields.

Rules:
1. For each OTTO field, find the single best matching PlentyONE source field.
2. Assign a confidence score from 0.0 to 1.0 based on semantic similarity.
3. Provide a short reason (max 15 words) explaining the match.
4. If no suitable PlentyONE field exists, set plenty_field to null and confidence to 0.
5. Return ONLY a valid JSON object with key "mappings" containing an array of mapping objects.
6. Each mapping object must have: otto_field, plenty_field (or null), confidence, reason.
7. Consider field types: do not map a numeric OTTO field to a free-text PlentyONE field if a typed alternative exists.
{$langNote}
PROMPT;
    }

    /**
     * Build the user prompt containing all field definitions.
     */
    private function buildUserPrompt(array $ottoFields, array $plentyFields, string $language): string
    {
        $ottoJson   = json_encode($ottoFields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $plentyJson = json_encode($plentyFields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $task = $language === 'de'
            ? 'Ordne die OTTO-Felder den passenden PlentyONE-Feldern zu.'
            : 'Map the OTTO fields to the appropriate PlentyONE fields.';

        return <<<PROMPT
{$task}

## OTTO Market target fields:
{$ottoJson}

## PlentyONE source fields:
{$plentyJson}

Return JSON: {"mappings": [...]}
PROMPT;
    }

    /**
     * Execute the HTTP call to the OpenAI API.
     */
    private function callOpenAi(string $apiKey, array $payload): array
    {
        $ch = curl_init(self::OPENAI_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT        => 60,
        ]);

        $raw   = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            $this->getLogger('OttoAiMapper')->error('OpenAI cURL error', ['errno' => $errno, 'error' => $error]);
            throw new \RuntimeException('Network error while contacting OpenAI: ' . $error);
        }

        $decoded = json_decode($raw, true);

        if ($httpCode !== 200) {
            $message = $decoded['error']['message'] ?? 'Unknown OpenAI error';
            $this->getLogger('OttoAiMapper')->error('OpenAI API error', ['http_code' => $httpCode, 'message' => $message]);
            throw new \RuntimeException('OpenAI API error (' . $httpCode . '): ' . $message);
        }

        return $decoded;
    }

    /**
     * Parse the raw OpenAI response into a clean mapping array.
     */
    private function parseResponse(array $response, array $ottoFields): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '{}';
        $data    = json_decode($content, true);

        if (!isset($data['mappings']) || !is_array($data['mappings'])) {
            $this->getLogger('OttoAiMapper')->warning('OpenAI returned unexpected structure', ['content' => $content]);
            return $this->buildEmptyMappings($ottoFields);
        }

        $threshold = (float) $this->config->get('OttoAiMapper.confidence_threshold', '0.75');

        return array_map(function (array $mapping) use ($threshold) {
            return [
                'otto_field'    => $mapping['otto_field']    ?? '',
                'plenty_field'  => $mapping['plenty_field']  ?? null,
                'confidence'    => (float) ($mapping['confidence'] ?? 0),
                'reason'        => $mapping['reason']        ?? '',
                'above_threshold' => ((float) ($mapping['confidence'] ?? 0)) >= $threshold,
            ];
        }, $data['mappings']);
    }

    /**
     * Fallback: return empty mappings for all OTTO fields.
     */
    private function buildEmptyMappings(array $ottoFields): array
    {
        return array_map(fn($f) => [
            'otto_field'      => $f['key'],
            'plenty_field'    => null,
            'confidence'      => 0.0,
            'reason'          => 'No mapping found',
            'above_threshold' => false,
        ], $ottoFields);
    }
}

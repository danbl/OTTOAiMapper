<?php

namespace OttoAiMapper\Services;

use Plenty\Modules\Plugin\Storage\Contracts\StorageRepositoryContract;

/**
 * Class MappingStorageService
 *
 * Persists AI mapping results using PlentyONE's plugin storage (key-value store).
 * Also maintains a history log of past mappings per catalog.
 *
 * @package OttoAiMapper\Services
 */
class MappingStorageService
{
    private const STORAGE_NAMESPACE = 'OttoAiMapper';
    private const MAX_HISTORY       = 20;

    /** @var StorageRepositoryContract */
    private StorageRepositoryContract $storage;

    public function __construct(StorageRepositoryContract $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Save a mapping result for a catalog.
     */
    public function saveResult(int $catalogId, array $result): void
    {
        $key = $this->resultKey($catalogId);
        $this->storage->uploadObject(
            self::STORAGE_NAMESPACE,
            $key,
            json_encode($result, JSON_PRETTY_PRINT)
        );

        $this->appendHistory($catalogId, $result);
    }

    /**
     * Load the latest mapping result for a catalog.
     */
    public function loadResult(int $catalogId): ?array
    {
        try {
            $object = $this->storage->getObject(self::STORAGE_NAMESPACE, $this->resultKey($catalogId));
            return json_decode($object->body, true);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Delete the mapping result for a catalog.
     */
    public function deleteResult(int $catalogId): void
    {
        try {
            $this->storage->deleteObject(self::STORAGE_NAMESPACE, $this->resultKey($catalogId));
        } catch (\Throwable $e) {
            // Object may not exist – silently ignore
        }
    }

    /**
     * Load the mapping history across all catalogs.
     */
    public function loadHistory(): array
    {
        try {
            $object = $this->storage->getObject(self::STORAGE_NAMESPACE, 'history.json');
            return json_decode($object->body, true) ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function resultKey(int $catalogId): string
    {
        return "mapping_result_{$catalogId}.json";
    }

    private function appendHistory(int $catalogId, array $result): void
    {
        $history = $this->loadHistory();

        array_unshift($history, [
            'catalogId'   => $catalogId,
            'mappedAt'    => $result['mappedAt'],
            'totalFields' => $result['totalFields'],
            'mappedCount' => $result['mappedCount'],
            'auto_applied'=> $result['auto_applied'] ?? false,
        ]);

        // Keep only the last N entries
        $history = array_slice($history, 0, self::MAX_HISTORY);

        $this->storage->uploadObject(
            self::STORAGE_NAMESPACE,
            'history.json',
            json_encode($history, JSON_PRETTY_PRINT)
        );
    }
}

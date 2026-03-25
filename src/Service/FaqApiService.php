<?php

declare(strict_types=1);

namespace Itrblueboost\Service;

use Configuration;
use Context;
use Itrblueboost;

/**
 * Service for fetching FAQs from the external API with file-based caching.
 */
class FaqApiService
{
    /** @var ApiLogger */
    private $apiLogger;

    public function __construct(ApiLogger $apiLogger)
    {
        $this->apiLogger = $apiLogger;
    }

    /**
     * Get product FAQs from API (cached).
     *
     * @return array<int, array{question: string, answer: string, id_itrblueboost_product_faq: int}>
     */
    public function getProductFaqs(int $idProduct, string $langIso): array
    {
        $idShop = (int) Context::getContext()->shop->id;
        $cacheKey = $this->getCacheKey('product', $idProduct, $langIso, $idShop);

        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $faqs = $this->fetchFromApi('product', 'id_product', $idProduct, $langIso);

        $result = [];
        foreach ($faqs as $index => $faq) {
            $result[] = [
                'question' => $faq['question'] ?? '',
                'answer' => $faq['answer'] ?? '',
                'id_itrblueboost_product_faq' => $index + 1,
            ];
        }

        $this->writeToCache($cacheKey, $result);

        return $result;
    }

    /**
     * Get category FAQs from API (cached).
     *
     * @return array<int, array{question: string, answer: string, id_itrblueboost_category_faq: int}>
     */
    public function getCategoryFaqs(int $idCategory, string $langIso): array
    {
        $idShop = (int) Context::getContext()->shop->id;
        $cacheKey = $this->getCacheKey('category', $idCategory, $langIso, $idShop);

        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $faqs = $this->fetchFromApi('category', 'id_category', $idCategory, $langIso);

        $result = [];
        foreach ($faqs as $index => $faq) {
            $result[] = [
                'question' => $faq['question'] ?? '',
                'answer' => $faq['answer'] ?? '',
                'id_itrblueboost_category_faq' => $index + 1,
            ];
        }

        $this->writeToCache($cacheKey, $result);

        return $result;
    }

    /**
     * Clear the entire FAQ cache directory.
     */
    public function clearCache(): void
    {
        $cacheDir = $this->getCachePath();
        if (!is_dir($cacheDir)) {
            return;
        }

        $files = glob($cacheDir . '*.json');
        if (!is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            @unlink($file);
        }
    }

    /**
     * Fetch FAQs from the external API.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchFromApi(string $type, string $paramName, int $entityId, string $langIso): array
    {
        $endpoint = '/api/faq/list?' . http_build_query([
            'is_enabled' => 'true',
            'status' => 'accepted',
            'type' => $type,
            $paramName => $entityId,
        ]);

        $response = $this->apiLogger->call('GET', $endpoint, null, $type . '_faq');

        if (isset($response['success']) && $response['success'] === false) {
            return [];
        }

        // call() returns array_merge($decoded, ['http_code' => ...])
        // API may return {faqs: [...]} or {data: [...]} or plain array
        if (isset($response['faqs']) && is_array($response['faqs'])) {
            return $response['faqs'];
        }

        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        return [];
    }

    private function getCacheKey(string $type, int $entityId, string $langIso, int $idShop): string
    {
        return $type . '_' . $entityId . '_' . $langIso . '_' . $idShop;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function getFromCache(string $cacheKey): ?array
    {
        $filePath = $this->getCachePath() . $cacheKey . '.json';

        if (!is_file($filePath)) {
            return null;
        }

        $mtime = filemtime($filePath);
        if ($mtime === false || (time() - $mtime) > $this->getCacheTtl()) {
            @unlink($filePath);

            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * @param array<int, array<string, mixed>> $data
     */
    private function writeToCache(string $cacheKey, array $data): void
    {
        $cacheDir = $this->getCachePath();

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $filePath = $cacheDir . $cacheKey . '.json';
        @file_put_contents($filePath, json_encode($data));
    }

    private function getCachePath(): string
    {
        return _PS_MODULE_DIR_ . 'itrblueboost/var/cache/faq/';
    }

    private function getCacheTtl(): int
    {
        $ttl = (int) Configuration::get(Itrblueboost::CONFIG_FAQ_CACHE_TTL);

        return $ttl > 0 ? $ttl : 3600;
    }
}

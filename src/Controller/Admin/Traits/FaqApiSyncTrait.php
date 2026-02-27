<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin\Traits;

/**
 * Trait for syncing FAQ data with the API.
 *
 * Requires $this->apiLogger to be set (ApiLogger instance).
 */
trait FaqApiSyncTrait
{
    /**
     * Update FAQ on API.
     *
     * @param int $apiFaqId API FAQ ID
     * @param array<string, mixed> $data Data to send
     * @param string $faqType FAQ type ('product_faq' or 'category_faq')
     *
     * @return array{success: bool, message?: string}
     */
    private function updateFaqOnApi(int $apiFaqId, array $data, string $faqType = 'product_faq'): array
    {
        $response = $this->apiLogger->updateFaq($apiFaqId, $data, $faqType);

        if (!isset($response['success']) || !$response['success']) {
            return ['success' => false, 'message' => $response['message'] ?? 'Unknown error'];
        }

        return ['success' => true];
    }
}

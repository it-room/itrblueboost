<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin\Traits;

/**
 * Trait for syncing content data with the API.
 *
 * Requires $this->apiLogger to be set (ApiLogger instance).
 */
trait ContentApiSyncTrait
{
    /**
     * Update content on API.
     *
     * @param int $apiContentId API Content ID
     * @param array<string, mixed> $data Data to send
     *
     * @return array{success: bool, message?: string}
     */
    private function updateContentOnApi(int $apiContentId, array $data): array
    {
        $response = $this->apiLogger->updateContent($apiContentId, $data);

        if (!isset($response['success']) || !$response['success']) {
            return ['success' => false, 'message' => $response['message'] ?? 'Unknown error'];
        }

        return ['success' => true];
    }
}

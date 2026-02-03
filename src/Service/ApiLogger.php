<?php

declare(strict_types=1);

namespace Itrblueboost\Service;

use Configuration;
use Itrblueboost;
use Itrblueboost\Entity\ApiLog;
use Itrblueboost\Entity\CreditHistory;

/**
 * API service with logging capability.
 */
class ApiLogger
{
    private const API_BASE_URL = 'https://apitr-sf.itroom.fr';

    /**
     * Make an API call with logging.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint (e.g., /api/faq)
     * @param array<string, mixed>|null $data Request body data
     * @param string|null $context Context for logging (product_faq, category_faq, image, etc.)
     *
     * @return array{success: bool, data?: mixed, message?: string, http_code: int, raw_response?: string}
     */
    public function call(
        string $method,
        string $endpoint,
        ?array $data = null,
        ?string $context = null
    ): array {
        $apiKey = Configuration::get(Itrblueboost::CONFIG_API_KEY);

        if (empty($apiKey)) {
            return [
                'success' => false,
                'message' => 'API key not configured.',
                'http_code' => 0,
            ];
        }

        $url = self::API_BASE_URL . $endpoint;

        $headers = [
            'X-API-Key' => $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $startTime = microtime(true);
        $ch = curl_init();

        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = $key . ': ' . $value;
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_FOLLOWLOCATION => true,
        ];

        $method = strtoupper($method);

        switch ($method) {
            case 'POST':
                $options[CURLOPT_POST] = true;
                if ($data !== null) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;

            case 'PUT':
                $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                if ($data !== null) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;

            case 'DELETE':
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;

            default:
                // GET request, no special options needed
                break;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $duration = microtime(true) - $startTime;

        // Log the API call
        $errorMessage = null;
        if ($response === false) {
            $errorMessage = 'cURL error: ' . $curlError;
        } elseif ($httpCode < 200 || $httpCode >= 300) {
            $errorMessage = 'HTTP error: ' . $httpCode;
        }

        ApiLog::log(
            $method,
            $url,
            $data,
            $headers,
            $httpCode,
            $response !== false ? (string) $response : null,
            $duration,
            $errorMessage,
            $context
        );

        // Handle response
        if ($response === false) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $curlError,
                'http_code' => $httpCode,
            ];
        }

        $decoded = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $message = 'API error (HTTP ' . $httpCode . ')';
            if (is_array($decoded) && isset($decoded['message'])) {
                $message = $decoded['message'];
            } elseif (is_array($decoded) && isset($decoded['error'])) {
                $message = $decoded['error'];
            }

            return [
                'success' => false,
                'message' => $message,
                'http_code' => $httpCode,
                'raw_response' => $response,
            ];
        }

        if (!is_array($decoded)) {
            return [
                'success' => false,
                'message' => 'Invalid JSON response',
                'http_code' => $httpCode,
                'raw_response' => $response,
            ];
        }

        return array_merge($decoded, [
            'http_code' => $httpCode,
        ]);
    }

    /**
     * Get FAQ prompts (for both products and categories).
     *
     * @param string $context Context for logging
     *
     * @return array{success: bool, prompts?: array, message?: string}
     */
    public function getFaqPrompts(string $context = 'faq'): array
    {
        $result = $this->call('GET', '/api/faq/prompts', null, $context);

        if (!isset($result['success'])) {
            $result['success'] = isset($result['prompts']);
        }

        return $result;
    }

    /**
     * Generate FAQs for a product.
     *
     * @param int $promptId Prompt ID
     * @param array<string, mixed> $productData Product data
     * @param int|null $productId Product ID for history
     *
     * @return array{success: bool, data?: array, message?: string, credits_used?: int, credits_remaining?: int}
     */
    public function generateProductFaq(int $promptId, array $productData, ?int $productId = null): array
    {
        // Add id_product to product data
        if ($productId !== null) {
            $productData['id_product'] = $productId;
        }

        $result = $this->call('POST', '/api/faq', [
            'prompt_id' => $promptId,
            'type' => 'product',
            'product' => $productData,
        ], 'product_faq');

        // Log credit usage if successful
        if (isset($result['success']) && $result['success'] && isset($result['credits_used']) && $result['credits_used'] > 0) {
            CreditHistory::log(
                'product_faq',
                (int) $result['credits_used'],
                (int) ($result['credits_remaining'] ?? 0),
                $productId,
                'product',
                $productData['name'] ?? null
            );
        }

        return $result;
    }

    /**
     * Generate FAQs for a category.
     *
     * @param int $promptId Prompt ID
     * @param array<string, mixed> $categoryData Category data
     * @param int|null $categoryId Category ID for history
     *
     * @return array{success: bool, data?: array, message?: string, credits_used?: int, credits_remaining?: int}
     */
    public function generateCategoryFaq(int $promptId, array $categoryData, ?int $categoryId = null): array
    {
        // Add id_category to category data
        if ($categoryId !== null) {
            $categoryData['id_category'] = $categoryId;
        }

        $result = $this->call('POST', '/api/faq', [
            'prompt_id' => $promptId,
            'type' => 'category',
            'category' => $categoryData,
        ], 'category_faq');

        // Log credit usage if successful
        if (isset($result['success']) && $result['success'] && isset($result['credits_used']) && $result['credits_used'] > 0) {
            CreditHistory::log(
                'category_faq',
                (int) $result['credits_used'],
                (int) ($result['credits_remaining'] ?? 0),
                $categoryId,
                'category',
                $categoryData['name'] ?? null
            );
        }

        return $result;
    }

    /**
     * Update a FAQ on the API.
     *
     * @param int $apiFaqId API FAQ ID
     * @param array<string, mixed> $data Update data
     * @param string $context Context for logging
     *
     * @return array{success: bool, message?: string}
     */
    public function updateFaq(int $apiFaqId, array $data, string $context = 'faq'): array
    {
        return $this->call('PUT', '/api/faq/' . $apiFaqId, $data, $context);
    }

    /**
     * Get account information.
     *
     * @return array{success: bool, client?: array, services?: array, error?: string}
     */
    public function getAccountInfo(): array
    {
        return $this->call('GET', '/api', null, 'account');
    }

    /**
     * Get image prompts.
     *
     * @return array{success: bool, prompts?: array, message?: string}
     */
    public function getImagePrompts(): array
    {
        return $this->call('GET', '/api/image/prompts', null, 'image');
    }
}

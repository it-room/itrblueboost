<?php

declare(strict_types=1);

namespace Itrblueboost\Service;

use Configuration;
use Itrblueboost;

/**
 * Service for ITROOM API calls.
 */
class ApiService
{
    private const API_BASE_URL = 'https://apitr-sf.itroom.fr/api';

    /**
     * Get account information from API.
     *
     * @return array{success: bool, client?: array{name: string, credits: int}, services?: array{active: array, inactive: array}, error?: string}
     */
    public function getAccountInfo(): array
    {
        $apiKey = Configuration::get(Itrblueboost::CONFIG_API_KEY);

        if (empty($apiKey)) {
            return [
                'success' => false,
                'error' => 'API key not configured.',
            ];
        }

        $response = $this->callApi($apiKey);

        if ($response === null) {
            return [
                'success' => false,
                'error' => 'API call error. Please check your API key.',
            ];
        }

        $response['success'] = true;

        return $response;
    }

    /**
     * Check if an API key is configured.
     */
    public function hasApiKey(): bool
    {
        $apiKey = Configuration::get(Itrblueboost::CONFIG_API_KEY);

        return !empty($apiKey);
    }

    /**
     * Call the ITROOM API.
     *
     * @param array<string, mixed>|null $data
     *
     * @return array<string, mixed>|null
     */
    public function callApi(
        string $apiKey,
        string $endpoint = '',
        string $method = 'GET',
        ?array $data = null
    ): ?array {
        $ch = curl_init();

        $url = self::API_BASE_URL . ($endpoint ? '/' . ltrim($endpoint, '/') : '');

        $headers = [
            'X-API-Key: ' . $apiKey,
            'Accept: application/json',
        ];

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($method === 'POST' && $data !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }
}

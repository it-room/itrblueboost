<?php

declare(strict_types=1);

namespace Itrblueboost\Form;

use Configuration;
use Itrblueboost;
use Itrblueboost\Service\ApiService;
use PrestaShop\PrestaShop\Core\Configuration\DataConfigurationInterface;

/**
 * Handles configuration data persistence.
 */
class ConfigurationDataConfiguration implements DataConfigurationInterface
{
    private ApiService $apiService;

    public function __construct(ApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        return [
            'api_key' => Configuration::get(Itrblueboost::CONFIG_API_KEY) ?: '',
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<int, array<string, string>>
     */
    public function updateConfiguration(array $configuration): array
    {
        $errors = [];

        if (!$this->validateConfiguration($configuration)) {
            $errors[] = [
                'key' => 'Invalid configuration',
                'domain' => 'Modules.Itrblueboost.Admin',
                'parameters' => [],
            ];

            return $errors;
        }

        $apiKey = $configuration['api_key'] ?? '';

        Configuration::updateValue(Itrblueboost::CONFIG_API_KEY, $apiKey);

        $this->updateServiceStatus($apiKey);

        return $errors;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public function validateConfiguration(array $configuration): bool
    {
        return isset($configuration['api_key']) && is_string($configuration['api_key']);
    }

    /**
     * Fetch and store service status from API.
     */
    private function updateServiceStatus(string $apiKey): void
    {
        if (empty($apiKey)) {
            Configuration::updateValue(Itrblueboost::CONFIG_SERVICE_FAQ, 0);
            Configuration::updateValue(Itrblueboost::CONFIG_SERVICE_IMAGE, 0);
            Configuration::updateValue(Itrblueboost::CONFIG_SERVICE_CATEGORY_FAQ, 0);
            Configuration::updateValue(Itrblueboost::CONFIG_CREDITS_REMAINING, '');

            return;
        }

        $accountInfo = $this->apiService->getAccountInfo();

        if (!$accountInfo['success'] || !isset($accountInfo['services'])) {
            Configuration::updateValue(Itrblueboost::CONFIG_SERVICE_FAQ, 0);
            Configuration::updateValue(Itrblueboost::CONFIG_SERVICE_IMAGE, 0);
            Configuration::updateValue(Itrblueboost::CONFIG_SERVICE_CATEGORY_FAQ, 0);

            return;
        }

        if (isset($accountInfo['client']['credits'])) {
            Configuration::updateValue(
                Itrblueboost::CONFIG_CREDITS_REMAINING,
                (int) $accountInfo['client']['credits']
            );
        }

        $activeServices = $accountInfo['services']['active'] ?? [];

        $faqActive = $this->isServiceActive($activeServices, ['faq', 'product_faq', 'faq_product', 'qa', 'question']);
        $imageActive = $this->isServiceActive($activeServices, ['image', 'product_image', 'image_product', 'img']);
        $categoryFaqActive = $this->isServiceActive($activeServices, ['category_faq', 'faq_category', 'cat_faq', 'category_qa']);

        Configuration::updateValue(Itrblueboost::CONFIG_SERVICE_FAQ, $faqActive ? 1 : 0);
        Configuration::updateValue(Itrblueboost::CONFIG_SERVICE_IMAGE, $imageActive ? 1 : 0);
        Configuration::updateValue(Itrblueboost::CONFIG_SERVICE_CATEGORY_FAQ, $categoryFaqActive ? 1 : 0);
    }

    /**
     * Check if a service is active.
     *
     * @param array<int, array<string, mixed>> $activeServices
     * @param array<string> $serviceCodes Possible service codes to match
     */
    private function isServiceActive(array $activeServices, array $serviceCodes): bool
    {
        foreach ($activeServices as $service) {
            $code = strtolower($service['code'] ?? '');

            foreach ($serviceCodes as $searchCode) {
                $search = strtolower($searchCode);
                if ($code === $search || strpos($code, $search) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}

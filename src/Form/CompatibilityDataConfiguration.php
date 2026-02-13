<?php

declare(strict_types=1);

namespace Itrblueboost\Form;

use Configuration;
use Itrblueboost;
use PrestaShop\PrestaShop\Core\Configuration\DataConfigurationInterface;

/**
 * Handles compatibility configuration data persistence.
 */
class CompatibilityDataConfiguration implements DataConfigurationInterface
{
    private const ALLOWED_VERSIONS = ['bootstrap4', 'bootstrap4alpha', 'bootstrap5'];
    private const ALLOWED_MODES = ['prod', 'test'];

    /**
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        return [
            'bootstrap_version' => Configuration::get(Itrblueboost::CONFIG_BOOTSTRAP_VERSION) ?: 'bootstrap5',
            'api_mode' => Configuration::get(Itrblueboost::CONFIG_API_MODE) ?: 'prod',
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

        Configuration::updateValue(
            Itrblueboost::CONFIG_BOOTSTRAP_VERSION,
            $configuration['bootstrap_version']
        );

        Configuration::updateValue(
            Itrblueboost::CONFIG_API_MODE,
            $configuration['api_mode']
        );

        return $errors;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public function validateConfiguration(array $configuration): bool
    {
        if (!isset($configuration['bootstrap_version'])) {
            return false;
        }

        if (!in_array($configuration['bootstrap_version'], self::ALLOWED_VERSIONS, true)) {
            return false;
        }

        if (!isset($configuration['api_mode'])) {
            return false;
        }

        return in_array($configuration['api_mode'], self::ALLOWED_MODES, true);
    }
}

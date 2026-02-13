<?php

declare(strict_types=1);

namespace Itrblueboost\Form;

use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;

/**
 * Fournit les données pour le formulaire de compatibilité.
 */
class CompatibilityFormDataProvider implements FormDataProviderInterface
{
    /** @var CompatibilityDataConfiguration */
    private $dataConfiguration;

    /**
     * @param CompatibilityDataConfiguration $dataConfiguration Service de configuration
     */
    public function __construct(CompatibilityDataConfiguration $dataConfiguration)
    {
        $this->dataConfiguration = $dataConfiguration;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->dataConfiguration->getConfiguration();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<int, array<string, string>>
     */
    public function setData(array $data): array
    {
        return $this->dataConfiguration->updateConfiguration($data);
    }
}

<?php

declare(strict_types=1);

namespace Itrblueboost\Form;

use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;

/**
 * Fournit les données pour le formulaire de configuration.
 */
class ConfigurationFormDataProvider implements FormDataProviderInterface
{
    /** @var ConfigurationDataConfiguration */
    private ConfigurationDataConfiguration $dataConfiguration;

    /**
     * @param ConfigurationDataConfiguration $dataConfiguration Service de configuration
     */
    public function __construct(ConfigurationDataConfiguration $dataConfiguration)
    {
        $this->dataConfiguration = $dataConfiguration;
    }

    /**
     * Retourne les données pour pré-remplir le formulaire.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->dataConfiguration->getConfiguration();
    }

    /**
     * Persiste les données soumises.
     *
     * @param array<string, mixed> $data Données du formulaire
     *
     * @return array<int, array<string, string>> Erreurs éventuelles
     */
    public function setData(array $data): array
    {
        return $this->dataConfiguration->updateConfiguration($data);
    }
}

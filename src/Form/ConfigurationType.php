<?php

declare(strict_types=1);

namespace Itrblueboost\Form;

use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Formulaire de configuration du module.
 */
class ConfigurationType extends TranslatorAwareType
{
    /**
     * Construit le formulaire de configuration.
     *
     * @param FormBuilderInterface $builder Builder du formulaire
     * @param array<string, mixed> $options Options du formulaire
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('api_key', TextType::class, [
                'label' => $this->trans('Clé API', 'Modules.Itrblueboost.Admin'),
                'help' => $this->trans(
                    'Entrez votre clé API ITROOM pour activer la synchronisation.',
                    'Modules.Itrblueboost.Admin'
                ),
                'required' => false,
                'attr' => [
                    'placeholder' => $this->trans('Votre clé API...', 'Modules.Itrblueboost.Admin'),
                ],
            ]);
    }
}

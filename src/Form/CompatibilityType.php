<?php

declare(strict_types=1);

namespace Itrblueboost\Form;

use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Formulaire de compatibilité du module.
 */
class CompatibilityType extends TranslatorAwareType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('bootstrap_version', ChoiceType::class, [
                'label' => $this->trans('Bootstrap version', 'Modules.Itrblueboost.Admin'),
                'help' => $this->trans(
                    'Select the Bootstrap version used on your theme.',
                    'Modules.Itrblueboost.Admin'
                ),
                'choices' => [
                    'Bootstrap 4' => 'bootstrap4',
                    'Bootstrap 4 Alpha' => 'bootstrap4alpha',
                    'Bootstrap 5' => 'bootstrap5',
                ],
                'required' => true,
            ])
            ->add('api_mode', ChoiceType::class, [
                'label' => $this->trans('API mode', 'Modules.Itrblueboost.Admin'),
                'help' => $this->trans(
                    'Select the API environment. Use Production by default.',
                    'Modules.Itrblueboost.Admin'
                ),
                'choices' => [
                    'Production' => 'prod',
                    'Test (blueboost.itroom.fr)' => 'test',
                ],
                'required' => true,
            ]);
    }
}

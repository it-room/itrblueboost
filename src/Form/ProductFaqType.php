<?php

declare(strict_types=1);

namespace Itrblueboost\Form;

use PrestaShopBundle\Form\Admin\Type\FormattedTextareaType;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatableType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProductFaqType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id_product', HiddenType::class)
            ->add('question', TranslatableType::class, [
                'label' => $this->trans('Question', 'Modules.Itrblueboost.Admin'),
                'type' => TextareaType::class,
                'required' => true,
                'options' => [
                    'constraints' => [
                        new NotBlank([
                            'message' => $this->trans('La question est obligatoire.', 'Modules.Itrblueboost.Admin'),
                        ]),
                    ],
                    'attr' => [
                        'rows' => 1,
                        'style' => 'resize: none; overflow: hidden;',
                    ],
                ],
            ])
            ->add('answer', TranslatableType::class, [
                'label' => $this->trans('Réponse', 'Modules.Itrblueboost.Admin'),
                'type' => FormattedTextareaType::class,
                'required' => false,
                'options' => [
                    'required' => false,
                ],
            ])
            ->add('active', SwitchType::class, [
                'label' => $this->trans('Actif', 'Modules.Itrblueboost.Admin'),
                'required' => false,
            ]);

        // Add modification reason field if FAQ has API ID
        if ($options['show_modification_reason']) {
            $builder->add('modification_reason', TextareaType::class, [
                'label' => $this->trans('Raison de la modification', 'Modules.Itrblueboost.Admin'),
                'required' => false,
                'attr' => [
                    'rows' => 2,
                    'placeholder' => $this->trans('Expliquez pourquoi vous modifiez cette FAQ (optionnel)', 'Modules.Itrblueboost.Admin'),
                ],
                'help' => $this->trans('Cette information sera envoyée à l\'API pour traçabilité.', 'Modules.Itrblueboost.Admin'),
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'Modules.Itrblueboost.Admin',
            'show_modification_reason' => false,
        ]);

        $resolver->setAllowedTypes('show_modification_reason', 'bool');
    }
}

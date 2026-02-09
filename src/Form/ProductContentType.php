<?php

declare(strict_types=1);

namespace Itrblueboost\Form;

use PrestaShopBundle\Form\Admin\Type\FormattedTextareaType;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatableType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProductContentType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id_product', HiddenType::class)
            ->add('content_type', ChoiceType::class, [
                'label' => $this->trans('Content type', 'Modules.Itrblueboost.Admin'),
                'choices' => [
                    $this->trans('Description', 'Modules.Itrblueboost.Admin') => 'description',
                    $this->trans('Short description', 'Modules.Itrblueboost.Admin') => 'description_short',
                ],
                'required' => true,
            ])
            ->add('generated_content', TranslatableType::class, [
                'label' => $this->trans('Generated content', 'Modules.Itrblueboost.Admin'),
                'type' => FormattedTextareaType::class,
                'required' => true,
                'options' => [
                    'constraints' => [
                        new NotBlank([
                            'message' => $this->trans('Content is required.', 'Modules.Itrblueboost.Admin'),
                        ]),
                    ],
                ],
            ])
            ->add('active', SwitchType::class, [
                'label' => $this->trans('Active', 'Modules.Itrblueboost.Admin'),
                'required' => false,
            ]);

        if ($options['show_modification_reason']) {
            $builder->add('modification_reason', TextareaType::class, [
                'label' => $this->trans('Modification reason', 'Modules.Itrblueboost.Admin'),
                'required' => false,
                'attr' => [
                    'rows' => 2,
                    'placeholder' => $this->trans('Explain why you are modifying this content (optional)', 'Modules.Itrblueboost.Admin'),
                ],
                'help' => $this->trans('This information will be sent to the API for traceability.', 'Modules.Itrblueboost.Admin'),
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

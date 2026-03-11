<?php

declare(strict_types=1);

namespace Itrblueboost\Form;

use PrestaShopBundle\Form\Admin\Type\FormattedTextareaType;
use PrestaShopBundle\Form\Admin\Type\TranslatableType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class CategoryContentType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id_category', HiddenType::class)
            ->add('generated_content', TranslatableType::class, [
                'label' => $this->trans('Description', 'Modules.Itrblueboost.Admin'),
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
            ->add('generated_content_short', TranslatableType::class, [
                'label' => $this->trans('Additional description', 'Modules.Itrblueboost.Admin'),
                'type' => FormattedTextareaType::class,
                'required' => false,
            ])
            ->add('meta_title', TranslatableType::class, [
                'label' => $this->trans('Meta title', 'Modules.Itrblueboost.Admin'),
                'type' => TextType::class,
                'required' => false,
                'options' => [
                    'attr' => ['maxlength' => 255],
                ],
            ])
            ->add('meta_description', TranslatableType::class, [
                'label' => $this->trans('Meta description', 'Modules.Itrblueboost.Admin'),
                'type' => TextareaType::class,
                'required' => false,
                'options' => [
                    'attr' => ['rows' => 2, 'maxlength' => 512],
                ],
            ])
            ->add('meta_keywords', TranslatableType::class, [
                'label' => $this->trans('Meta keywords', 'Modules.Itrblueboost.Admin'),
                'type' => TextType::class,
                'required' => false,
                'options' => [
                    'attr' => ['maxlength' => 512],
                ],
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

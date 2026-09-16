<?php

namespace SEOne\Form;

use SEOne\SEOne;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class SeoForm extends BaseForm
{
    protected function buildForm(): void
    {
        $form = $this->formBuilder;
        $form
            ->add(
                'noindex_checkbox',
                IntegerType::class,
                [
                    'required' => false,
                    'label' => Translator::getInstance()->trans(
                        'noindex',
                        [],
                        SEOne::DOMAIN_NAME
                    ),
                    'label_attr' => [
                        'for' => 'noindex_checkbox',
                    ],
                ]
            )
            ->add(
                'nofollow_checkbox',
                IntegerType::class,
                [
                    'required' => false,
                    'label' => Translator::getInstance()->trans(
                        'nofollow',
                        [],
                        SEOne::DOMAIN_NAME
                    ),
                    'label_attr' => [
                        'for' => 'nofollow_checkbox',
                    ],
                ]
            )
            ->add(
                'h1',
                TextType::class,
                [
                    'required' => false,
                    'label' => Translator::getInstance()->trans(
                        'h1',
                        [],
                        SEOne::DOMAIN_NAME
                    ),
                    'label_attr' => [
                        'for' => 'h1',
                    ],
                ]
            )
            ->add(
                'json_data',
                TextareaType::class,
                [
                    'required' => false,
                    'label' => Translator::getInstance()->trans(
                        'JSON structured data',
                        [],
                        SEOne::DOMAIN_NAME
                    ),
                    'label_attr' => [
                        'for' => 'json_data',
                    ],
                ]
            );

        for ($i = 1; $i <= 5; ++$i) {
            $form
                ->add(
                    'mesh_text_'.$i,
                    TextType::class,
                    [
                        'required' => false,
                        'label' => Translator::getInstance()->trans(
                            'text',
                            [],
                            SEOne::DOMAIN_NAME
                        ),
                        'label_attr' => [
                            'for' => 'mesh_text_'.$i,
                        ],
                    ]
                )
                ->add(
                    'mesh_url_'.$i,
                    UrlType::class,
                    [
                        'required' => false,
                        'default_protocol' => 'https',
                        'label' => Translator::getInstance()->trans(
                            'url',
                            [],
                            SEOne::DOMAIN_NAME
                        ),
                        'label_attr' => [
                            'for' => 'mesh_url_'.$i,
                        ],
                    ]
                )
                ->add(
                    'mesh_'.$i,
                    TextType::class,
                    [
                        'required' => false,
                        'label' => Translator::getInstance()->trans(
                            'text',
                            [],
                            SEOne::DOMAIN_NAME
                        ),
                        'label_attr' => [
                            'for' => 'mesh_'.$i,
                        ],
                    ]
                );
        }
    }

    public static function getName(): string
    {
        return 'seone_form';
    }
}

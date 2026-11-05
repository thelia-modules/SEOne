<?php

namespace SEOne\Form;

use SEOne\SEOne;
use SEOne\Service\MetaTemplate\EditionLanguageResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class StoreSeoForm extends BaseForm
{
    public function __construct(private readonly EditionLanguageResolver $editionLanguageResolver)
    {
    }

    public static function getName(): string
    {
        return 'seone_store_form_config';
    }

    protected function buildForm(): void
    {
        // The language the controller saves into (see ConfigurationController::saveStoreConfiguration()).
        $locale = $this->editionLanguageResolver->resolveLocale();

        $this->formBuilder
            ->add(
                'title',
                TextType::class,
                [
                    'required' => false,
                    'label' => Translator::getInstance()?->trans('Store name', [], SEOne::DOMAIN_NAME),
                    'data' => SEOne::getConfigValue('title', null, $locale),
                ]
            )
            ->add(
                'description',
                TextType::class,
                [
                    'required' => false,
                    'label' => Translator::getInstance()?->trans('Store description', [], SEOne::DOMAIN_NAME),
                    'data' => SEOne::getConfigValue('description', null, $locale),
                ]
            )
            ->add(
                'keywords',
                TextType::class,
                [
                    'required' => false,
                    'label' => Translator::getInstance()?->trans('Keywords', [], SEOne::DOMAIN_NAME),
                    'data' => SEOne::getConfigValue('keywords', null, $locale),
                ]
            )
        ;
    }
}

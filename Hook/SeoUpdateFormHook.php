<?php

namespace SEOne\Hook;

use SEOne\SEOne;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Form\SeoForm as TheliaSeoForm;
use Thelia\Model\MetaDataQuery;

class SeoUpdateFormHook extends BaseHook
{
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public function addInputs(HookRenderEvent $event): void
    {
        $id = $event->getArgument('id');
        $type = $event->getArgument('type');

        $canonical = null;
        $canonicalMetaData = MetaDataQuery::create()
            ->filterByMetaKey(SEOne::SEO_CANONICAL_META_KEY)
            ->filterByElementKey($type)
            ->filterByElementId($id)
            ->findOneOrCreate();

        $canonicalMetaDataValues = json_decode((string) $canonicalMetaData->getValue(), true);

        $lang = $this->getSession()->getAdminEditionLang();

        if (isset($canonicalMetaDataValues[$lang->getLocale()])) {
            $canonical = $canonicalMetaDataValues[$lang->getLocale()];
        }

        // The Smarty back-office renders this hook inside an open {form} block, so the template
        // could rely on the parser context current form. The Twig back-office has no Smarty form
        // context: always provide the SEO form (the canonical field is added to it by
        // SeoFormListener on FORM_AFTER_BUILD).
        $form = $event->getArgument('form');

        if (null === $form) {
            $form = $this->formFactory->createForm(TheliaSeoForm::getName());
            $form->createView();
        }

        $event->add($this->render(
            'hook-seo-update-form.html',
            [
                'form' => $form,
                'canonical' => $canonical,
            ]
        ));
    }
}

<?php

namespace SEOne\Hook;

use SEOne\Form\SeoForm;
use SEOne\Model\SeoneQuery;
use SEOne\SEOne;
use SEOne\Service\MetaTemplate\MetaTemplateField;
use SEOne\Service\MetaTemplate\MetaTemplateService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\MetaDataQuery;

class SeoFormHook extends BaseHook
{
    use TemplateFallbackTrait;

    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        private readonly MetaTemplateService $metaTemplateService,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public function onTabSeoUpdateForm(HookRenderEvent $event): void
    {
        $objectId = (int) $event->getArgument('id');
        $objectType = (string) $event->getArgument('type');

        if ($objectId <= 0 || '' === $objectType) {
            return;
        }

        $lang = $this->getSession()->getAdminEditionLang();

        $event->add(
            $this->render(
                'SEOne/seo-additional-fields.html.twig',
                [
                    'form' => $this->formFactory->createForm(SeoForm::getName())->createView()->getView(),
                    'object_id' => $objectId,
                    'object_type' => $objectType,
                    'edit_language_id' => $lang->getId(),
                    'values' => $this->loadValues($objectId, $objectType, $lang->getLocale()),
                    'canonical' => $this->loadCanonical($objectId, $objectType, $lang->getLocale()),
                    'meta_template_hint' => $this->loadMetaTemplateHint($objectId, $objectType, $lang->getLocale()),
                ]
            )
        );
    }

    /**
     * What the configured templates would produce for this entity, so the merchant sees what
     * an empty meta field falls back to. Empty when no resolver serves this kind of page.
     *
     * @return array<string, string>
     */
    private function loadMetaTemplateHint(int $objectId, string $objectType, string $locale): array
    {
        if (null === $this->metaTemplateService->getResolver($objectType)) {
            return [];
        }

        return [
            'title' => $this->metaTemplateService->render($objectType, MetaTemplateField::Title, $objectId, $locale),
            'description' => $this->metaTemplateService->render($objectType, MetaTemplateField::Description, $objectId, $locale),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadValues(int $objectId, string $objectType, string $locale): array
    {
        $seo = SeoneQuery::create()
            ->filterByObjectId($objectId)
            ->filterByObjectType($objectType)
            ->findOne();

        if (null === $seo) {
            return [];
        }

        $seo->setLocale($locale);

        $values = [
            'noindex' => (int) $seo->getNoindex(),
            'nofollow' => (int) $seo->getNofollow(),
            'h1' => (string) $seo->getH1(),
            'json_data' => (string) $seo->getJsonData(),
        ];

        for ($i = 1; $i <= 5; ++$i) {
            $values['mesh_'.$i] = (string) $seo->{'getMesh'.$i}();
            $values['mesh_text_'.$i] = (string) $seo->{'getMeshText'.$i}();
            $values['mesh_url_'.$i] = (string) $seo->{'getMeshUrl'.$i}();
        }

        return $values;
    }

    private function loadCanonical(int $objectId, string $objectType, string $locale): string
    {
        $metaData = MetaDataQuery::create()
            ->filterByMetaKey(SEOne::SEO_CANONICAL_META_KEY)
            ->filterByElementKey($objectType)
            ->filterByElementId($objectId)
            ->findOne();

        if (null === $metaData) {
            return '';
        }

        $byLocale = json_decode((string) $metaData->getValue(), true);

        return \is_array($byLocale) && isset($byLocale[$locale]) ? (string) $byLocale[$locale] : '';
    }
}

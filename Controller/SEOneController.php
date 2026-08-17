<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SEOne\Controller;

use Propel\Runtime\Exception\PropelException;
use SEOne\Form\SeoForm;
use SEOne\Model\Seone;
use SEOne\Model\SeoneQuery;
use SEOne\SEOne as SEOneModule;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Model\LangQuery;
use Thelia\Model\MetaDataQuery;
use Thelia\Tools\URL;

#[Route('/admin/module/seone/seo', name: 'seone_seo', methods: 'POST')]
class SEOneController extends BaseAdminController
{
    /**
     * @throws PropelException
     */
    #[Route('/save', name: '_save', methods: 'POST')]
    public function saveAction(Request $request): RedirectResponse
    {
        $form = $this->createForm(name: SeoForm::getName());

        $seoForm = $this->validateForm($form);

        // The back-office form posts to path('seone_seo_save', {object_id, object_type,
        // lang_id}), so these three arrive in the query string, not in the body — reading
        // them from the body only would break saving. Query first, then body, which is the
        // order the Request::get() this replaces used.
        $object_id = $request->query->get('object_id') ?? $request->request->get('object_id');
        $object_type = $request->query->get('object_type') ?? $request->request->get('object_type');

        $lang = LangQuery::create()
            ->filterById($request->query->get('lang_id') ?? $request->request->get('lang_id'))
            ->findOne();

        if (null === $objectSeo = SeoneQuery::create()
                ->filterByObjectId($object_id)
                ->filterByObjectType($object_type)
                ->findOne()
        ) {
            $objectSeo = (new Seone())
                ->setObjectId($object_id)
                ->setObjectType($object_type);
        }

        $objectSeo
            ->setLocale($lang->getLocale())
            ->setJsonData($seoForm->get('json_data')->getData())
            ->setNoindex(null === $seoForm->get('noindex_checkbox')->getData() ? 0 : 1)
            ->setNofollow(null === $seoForm->get('nofollow_checkbox')->getData() ? 0 : 1)
            ->setH1(null === $seoForm->get('h1')->getData() ? '' : $seoForm->get('h1')->getData());

        for ($i = 1; $i <= 5; ++$i) {
            \call_user_func([$objectSeo, 'setMeshUrl'.$i], $seoForm->get('mesh_url_'.$i)->getData());
            \call_user_func([$objectSeo, 'setMeshText'.$i], $seoForm->get('mesh_text_'.$i)->getData());
            \call_user_func([$objectSeo, 'setMesh'.$i], $seoForm->get('mesh_'.$i)->getData());
        }

        $objectSeo->save();

        // Canonical URL override is stored as per-locale metadata (same storage the front and the
        // legacy SeoFormListener use), saved here so the whole SEO block has a single Save button.
        $canonicalMetaData = MetaDataQuery::create()
            ->filterByMetaKey(SEOneModule::SEO_CANONICAL_META_KEY)
            ->filterByElementKey($object_type)
            ->filterByElementId($object_id)
            ->findOneOrCreate();

        $canonicalValues = $canonicalMetaData->isNew()
            ? []
            : (json_decode((string) $canonicalMetaData->getValue(), true) ?: []);
        $canonicalValues[$lang->getLocale()] = (string) $request->request->get('canonical', '');

        $canonicalMetaData
            ->setIsSerialized(0)
            ->setValue(json_encode($canonicalValues))
            ->save();

        return $this->generateRedirect(
            URL::getInstance()->absoluteUrl(
                $request->getSession()->getReturnToUrl(),
                ['current_tab' => 'seo']
            )
        );
    }
}

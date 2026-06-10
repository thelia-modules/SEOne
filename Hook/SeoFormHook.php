<?php

namespace SEOne\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class SeoFormHook extends BaseHook
{
    public function onTabSeoUpdateForm(HookRenderEvent $event): void
    {
        $objectId = $event->getArgument('id');
        $objectType = $event->getArgument('type');

        $event->add(
            $this->render(
                'seo-additional-fields.html',
                [
                    'object_id' => $objectId,
                    'object_type' => $objectType,
                    // The Smarty back-office exposes this as a global template variable, but the
                    // hook can also be rendered from the Twig back-office where no such global
                    // exists: always provide it explicitly.
                    'edit_language_id' => $this->getSession()->getAdminEditionLang()->getId(),
                ]
            )
        );
    }
}

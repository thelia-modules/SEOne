<?php

declare(strict_types=1);

namespace SEOne\Hook;

use SEOne\Service\HeadBottomRenderer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;

/**
 * The `main.head-bottom` hook of the themes that still call the Thelia 2 front hooks.
 * It prints what `layout.head.bottom` prints, once per request.
 */
class MetaHook extends BaseHook
{
    public function __construct(
        private readonly HeadBottomRenderer $headBottomRenderer,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public function onMainHeadBottom(HookRenderEvent $event): void
    {
        $event->add($this->headBottomRenderer->render());
    }
}

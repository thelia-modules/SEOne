<?php

declare(strict_types=1);

namespace SEOne\Hook\Theme;

use SEOne\Service\HeadBottomRenderer;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Twig\Environment;

final readonly class SEOneThemeHook implements ThemeHookInterface
{
    public function __construct(
        private Environment $twig,
        private HeadBottomRenderer $headBottomRenderer,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return \in_array($hookName, ['layout.head.top', 'layout.head.bottom'], true);
    }

    public function render(string $hookName, array $parameters): string
    {
        return match ($hookName) {
            'layout.head.top' => $this->twig->render('@SEOneModule/theme-hook/head-top.html.twig',$parameters),
            'layout.head.bottom' => $this->headBottomRenderer->render($parameters),
            default => ''
        };
    }
}

<?php

namespace SEOne\Hook\Theme;

use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Twig\Environment;

final readonly class SEOneThemeHook implements ThemeHookInterface
{
    public function __construct(
        private Environment $twig,
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
            'layout.head.bottom' => $this->twig->render('@SEOneModule/theme-hook/head-bottom.html.twig',$parameters)
        };
    }
}

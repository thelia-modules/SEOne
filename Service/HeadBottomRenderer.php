<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SEOne\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * The markup the module adds at the bottom of the head: the robots meta tag and the JSON-LD.
 *
 * A theme reaches it through the `layout.head.bottom` theme hook, or through the legacy
 * `main.head-bottom` hook of the themes written for Thelia 2. A theme may call both: the
 * markup is printed once per request, by whichever hook comes first.
 */
final readonly class HeadBottomRenderer
{
    private const string RENDERED_ATTRIBUTE = '_seone_head_bottom_rendered';

    public function __construct(
        private Environment $twig,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function render(array $parameters = []): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (true === $request?->attributes->get(self::RENDERED_ATTRIBUTE)) {
            return '';
        }

        $markup = $this->twig->render('@SEOneModule/theme-hook/head-bottom.html.twig', $parameters);

        $request?->attributes->set(self::RENDERED_ATTRIBUTE, true);

        return $markup;
    }
}

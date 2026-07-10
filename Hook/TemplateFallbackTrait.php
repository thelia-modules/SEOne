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

declare(strict_types=1);

namespace SEOne\Hook;

use Thelia\Core\Template\ParserInterface;

/**
 * Lets the back-office hooks render on a legacy Smarty back-office.
 *
 * The hooks reference `.html.twig` templates, which only resolve when the
 * active back-office template is the recent Twig one (`default-twig`). When the
 * store still runs the Smarty back-office (`default`), those names cannot be
 * resolved and the hook renders an `ERR: Unknown template ...` string.
 *
 * The module ships both variants side by side, but with different layouts:
 *   - Twig:   default-twig/SEOne/module_configuration.html.twig  (module-code subfolder)
 *   - Smarty: default/module_configuration.html                  (flat, no subfolder)
 *
 * The hook render names carry the `SEOne/` prefix (Twig layout), so the Smarty
 * fallback strips both the `.twig` extension and the leading module-code
 * prefix, then keeps whichever candidate the active parser can actually resolve.
 */
trait TemplateFallbackTrait
{
    public function render(string $templateName, array $parameters = []): string
    {
        return parent::render($this->resolveCompatibleTemplateName($templateName), $parameters);
    }

    private function resolveCompatibleTemplateName(string $templateName): string
    {
        if (!str_ends_with($templateName, '.html.twig')) {
            return $templateName;
        }

        $parser = $this->getParser();

        if (!$parser instanceof ParserInterface) {
            return $templateName;
        }

        $resolver = $this->getAssetsResolver();
        $moduleCode = $this->module?->getCode() ?? '';

        // Recent Twig back-office: the .html.twig template resolves, keep it.
        if (null !== $resolver->resolveAssetSourcePath($moduleCode, '', $templateName, $parser)) {
            return $templateName;
        }

        // Legacy Smarty back-office: try the .html template, with and without the
        // leading module-code prefix (Smarty templates live flat under default/).
        $withoutTwig = substr($templateName, 0, -\strlen('.twig'));
        $candidates = [$withoutTwig];

        $prefix = $moduleCode.'/';

        if ('' !== $moduleCode && str_starts_with($withoutTwig, $prefix)) {
            $candidates[] = substr($withoutTwig, \strlen($prefix));
        }

        foreach ($candidates as $candidate) {
            if (null !== $resolver->resolveAssetSourcePath($moduleCode, '', $candidate, $parser)) {
                return $candidate;
            }
        }

        return $templateName;
    }
}

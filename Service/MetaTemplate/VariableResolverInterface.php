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

namespace SEOne\Service\MetaTemplate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Resolves the variables a meta template may use for one kind of page (a "view": product,
 * category, content, folder...). A module that serves its own pages declares one of these and
 * the templates screen, the validation and the rendering pick it up without any other wiring.
 */
#[AutoconfigureTag(VariableResolverInterface::TAG)]
interface VariableResolverInterface
{
    public const string TAG = 'seone.meta_template_variable_resolver';

    /**
     * The page kind this resolver serves, as the front routes name it: 'product', 'category'...
     */
    public function getView(): string;

    /**
     * Variable names without their % markers, in the order the configuration screen lists them.
     *
     * @return list<string>
     */
    public function getVariableNames(): array;

    /**
     * Plain-text values for the entity, one entry per name of getVariableNames(): '' when the
     * entity has no value for it (a product without brand), an empty array when the entity does
     * not exist. HTML fields (description, chapo) are returned stripped of their tags.
     *
     * @return array<string, string>
     */
    public function resolve(int $id, string $locale): array;
}

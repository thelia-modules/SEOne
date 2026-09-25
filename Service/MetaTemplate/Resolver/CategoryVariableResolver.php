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

namespace SEOne\Service\MetaTemplate\Resolver;

use SEOne\Service\MetaTemplate\HtmlToPlainText;
use SEOne\Service\MetaTemplate\VariableResolverInterface;
use SEOne\Service\SeoDefaultModels\LocalizedValueTrait;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ProductQuery;

/**
 * Variables of a category page.
 */
final readonly class CategoryVariableResolver implements VariableResolverInterface
{
    use LocalizedValueTrait;

    public function getView(): string
    {
        return 'category';
    }

    public function getVariableNames(): array
    {
        return [
            'title',
            'description',
            'chapo',
            'number_products',
            'category_parent_title',
        ];
    }

    public function resolve(int $id, string $locale): array
    {
        if ($id <= 0) {
            return [];
        }

        $category = CategoryQuery::create()->findPk($id);

        if (null === $category) {
            return [];
        }

        return [
            'title' => $this->localizedValue($category, 'getTitle', $locale),
            'description' => HtmlToPlainText::convert($this->localizedValue($category, 'getDescription', $locale)),
            'chapo' => HtmlToPlainText::convert($this->localizedValue($category, 'getChapo', $locale)),
            'number_products' => (string) ProductQuery::create()
                ->filterByCategory($category)
                ->filterByVisible(1)
                ->count(),
            'category_parent_title' => $this->parentTitle($category, $locale),
        ];
    }

    private function parentTitle(Category $category, string $locale): string
    {
        $parentId = $category->getParent();

        // The root of the tree is 0, not null: a top-level category has no parent to name.
        if (null === $parentId || $parentId <= 0) {
            return '';
        }

        return $this->localizedValue(CategoryQuery::create()->findPk($parentId), 'getTitle', $locale);
    }
}

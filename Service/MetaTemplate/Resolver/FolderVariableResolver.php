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
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;

/**
 * Variables of a folder page.
 */
final readonly class FolderVariableResolver implements VariableResolverInterface
{
    use LocalizedValueTrait;

    public function getView(): string
    {
        return 'folder';
    }

    public function getVariableNames(): array
    {
        return [
            'title',
            'description',
            'chapo',
            'folder_parent_title',
        ];
    }

    public function resolve(int $id, string $locale): array
    {
        if ($id <= 0) {
            return [];
        }

        $folder = FolderQuery::create()->findPk($id);

        if (null === $folder) {
            return [];
        }

        return [
            'title' => $this->localizedValue($folder, 'getTitle', $locale),
            'description' => HtmlToPlainText::convert($this->localizedValue($folder, 'getDescription', $locale)),
            'chapo' => HtmlToPlainText::convert($this->localizedValue($folder, 'getChapo', $locale)),
            'folder_parent_title' => $this->parentTitle($folder, $locale),
        ];
    }

    private function parentTitle(Folder $folder, string $locale): string
    {
        $parentId = $folder->getParent();

        // The root of the tree is 0, not null: a top-level folder has no parent to name.
        if (null === $parentId || $parentId <= 0) {
            return '';
        }

        return $this->localizedValue(FolderQuery::create()->findPk($parentId), 'getTitle', $locale);
    }
}

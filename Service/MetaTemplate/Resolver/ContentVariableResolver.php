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
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;

/**
 * Variables of a content page.
 */
final readonly class ContentVariableResolver implements VariableResolverInterface
{
    use LocalizedValueTrait;

    public function getView(): string
    {
        return 'content';
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

        $content = ContentQuery::create()->findPk($id);

        if (null === $content) {
            return [];
        }

        return [
            'title' => $this->localizedValue($content, 'getTitle', $locale),
            'description' => HtmlToPlainText::convert($this->localizedValue($content, 'getDescription', $locale)),
            'chapo' => HtmlToPlainText::convert($this->localizedValue($content, 'getChapo', $locale)),
            'folder_parent_title' => $this->defaultFolderTitle($content, $locale),
        ];
    }

    private function defaultFolderTitle(Content $content, string $locale): string
    {
        // 0 when the content hangs in no folder at all.
        $folderId = $content->getDefaultFolderId();

        if ($folderId <= 0) {
            return '';
        }

        return $this->localizedValue(FolderQuery::create()->findPk($folderId), 'getTitle', $locale);
    }
}

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

enum MetaTemplateField: string
{
    case Title = 'title';
    case Description = 'description';

    public function defaultMaxLength(): int
    {
        return match ($this) {
            self::Title => 60,
            self::Description => 160,
        };
    }
}

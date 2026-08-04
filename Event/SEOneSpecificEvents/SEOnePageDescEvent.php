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

namespace SEOne\Event\SEOneSpecificEvents;

use Thelia\Core\Event\ActionEvent;

class SEOnePageDescEvent extends ActionEvent
{
    protected string $desc = '';
    protected string $view;
    protected ?int $view_id;

    public const string BETTER_SEO_PAGE_DESC = 'better.seo.page.desc';

    public function __construct(string $view, ?int $view_id)
    {
        $this->view = $view;
        $this->view_id = $view_id;
    }

    public function getDesc(): string
    {
        return $this->desc;
    }

    public function setDesc(string $desc): self
    {
        $this->desc = $desc;

        return $this;
    }

    public function getView(): string
    {
        return $this->view;
    }

    public function setView(string $view): self
    {
        $this->view = $view;

        return $this;
    }

    public function getViewId(): ?int
    {
        return $this->view_id;
    }

    public function setViewId(?int $view_id): self
    {
        $this->view_id = $view_id;

        return $this;
    }
}

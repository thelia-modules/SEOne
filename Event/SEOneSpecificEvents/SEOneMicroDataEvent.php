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

class SEOneMicroDataEvent extends ActionEvent
{
    protected string $data = '';
    protected string $view;
    protected ?int $view_id;
    protected $parameters = [];
    public const string BETTER_SEO_MICRO_DATA = 'better.seo.page.micro.data';

    public function __construct(string $view, ?int $view_id, array $parameters)
    {
        $this->view = $view;
        $this->view_id = $view_id;
        $this->parameters = $parameters;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function setData(string $data): self
    {
        $this->data = $data;

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

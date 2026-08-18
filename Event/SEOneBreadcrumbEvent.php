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

namespace SEOne\Event;

use Thelia\Core\Event\ActionEvent;

class SEOneBreadcrumbEvent extends ActionEvent
{
    protected string $view;
    protected ?int $view_id;
    protected $parameters = [];
    protected array $breadcrumb = [];
    public const BETTER_SEO_BREADCRUMB = 'seone.page.breadcrumb';

    public function __construct(string $view, ?int $view_id, ?array $parameters)
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

    public function getBreadcrumb(): array
    {
        return $this->breadcrumb;
    }

    public function setBreadcrumb(array $breadcrumb): void
    {
        $this->breadcrumb = $breadcrumb;
    }

    public function getView(): string
    {
        return $this->view;
    }

    public function setView($view): void
    {
        $this->view = $view;
    }

    public function getViewId(): ?int
    {
        return $this->view_id;
    }

    public function setViewId($view_id): void
    {
        $this->view_id = $view_id;
    }
}

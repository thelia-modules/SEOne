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

namespace SEOne\Tests\Integration\Hook;

use PHPUnit\Framework\Attributes\Test;
use SEOne\Hook\Theme\SEOneThemeHook;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Test\IntegrationTestCase;

/**
 * The markup SEOne prints at the bottom of the head, for a theme that calls the `layout.head.bottom`
 * theme hook, for a theme still calling the `main.head-bottom` hook of Thelia 2, and for a theme
 * calling both: the page carries it once.
 */
final class HeadBottomHooksTest extends IntegrationTestCase
{
    /** Printed on every page: the structured data of the store. */
    private const string STORE_NODE = '"@type":"LocalBusiness"';

    protected function setUp(): void
    {
        parent::setUp();

        $this->startPage();
    }

    #[Test]
    public function aThemeCallingOnlyTheLegacyHookGetsTheMarkup(): void
    {
        self::assertSame(1, substr_count($this->legacyHook(), self::STORE_NODE));
    }

    #[Test]
    public function aThemeCallingOnlyTheThemeHookGetsTheMarkup(): void
    {
        self::assertSame(1, substr_count($this->themeHook(), self::STORE_NODE));
    }

    #[Test]
    public function aThemeCallingBothHooksGetsTheMarkupOnce(): void
    {
        $page = $this->themeHook().$this->legacyHook();

        self::assertSame(1, substr_count($page, self::STORE_NODE));
    }

    #[Test]
    public function theOrderOfTheTwoHooksDoesNotMatter(): void
    {
        $page = $this->legacyHook().$this->themeHook();

        self::assertSame(1, substr_count($page, self::STORE_NODE));
    }

    #[Test]
    public function bothHooksPrintTheSameMarkup(): void
    {
        $legacy = $this->legacyHook();

        $this->startPage();

        self::assertSame($this->themeHook(), $legacy);
    }

    #[Test]
    public function aNewPageGetsTheMarkupAgain(): void
    {
        $this->themeHook();

        $this->startPage();

        self::assertSame(1, substr_count($this->legacyHook(), self::STORE_NODE));
    }

    private function startPage(): void
    {
        $request = Request::create('http://localhost');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $requestStack = $this->getService(RequestStack::class);

        while (null !== $requestStack->pop()) {
        }

        $requestStack->push($request);
    }

    private function legacyHook(): string
    {
        $event = new HookRenderEvent('main.head-bottom');

        $this->getService(EventDispatcherInterface::class)->dispatch($event, 'hook.'.TemplateDefinition::FRONT_OFFICE.'.main.head-bottom');

        return $event->dump();
    }

    private function themeHook(): string
    {
        return $this->getService(SEOneThemeHook::class)->render('layout.head.bottom', ['breadcrumb' => []]);
    }
}

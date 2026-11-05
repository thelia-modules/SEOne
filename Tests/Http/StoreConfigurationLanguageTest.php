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

namespace SEOne\Tests\Http;

use PHPUnit\Framework\Attributes\Test;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use SEOne\SEOne;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\Admin;
use Thelia\Model\LangQuery;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * The store card of the SEOne configuration screen is shown and saved in the edition language
 * chosen on the screen, whatever the language of the back-office itself.
 */
final class StoreConfigurationLanguageTest extends WebIntegrationTestCase
{
    private const string SCREEN = '/admin/module/SEOne';

    // Neither the shop's default language nor the one an administrator works in by default:
    // a screen that ignores the edition language shows and saves another one.
    private const string EDITION_LOCALE = 'es_ES';

    private AdminSessionInjector $injector;

    private FixtureFactory $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->injector = new AdminSessionInjector();
        $this->getContainer()->get(EventDispatcherInterface::class)->addSubscriber($this->injector);

        // Built on the connection, not through createFixtureFactory(): no synthetic request
        // must become the main request of the client's calls.
        $this->fixtures = new FixtureFactory(Propel::getConnection('TheliaMain'));

        (new \ReflectionProperty(ParserResolver::class, 'currentParser'))->setValue(null, null);
    }

    protected function tearDown(): void
    {
        $this->injector->clear();

        parent::tearDown();
    }

    #[Test]
    public function theStoreCardShowsTheValuesOfTheEditionLanguage(): void
    {
        SEOne::setConfigValue('title', 'Título de la tienda', self::EDITION_LOCALE);
        $this->forgetReadSettings();
        $this->logIn($this->fixtures->admin());

        $form = $this->storeForm($this->editionLanguageId());

        self::assertSame('Título de la tienda', $form->get('seone_store_form_config[title]')->getValue());
        self::assertSame((string) $this->editionLanguageId(), $form->get('edit_language_id')->getValue());
    }

    #[Test]
    public function theStoreCardSavesIntoTheEditionLanguageOnly(): void
    {
        $otherLocales = LangQuery::create()->filterByLocale(self::EDITION_LOCALE, Criteria::NOT_EQUAL)->find();
        $before = [];
        foreach ($otherLocales as $lang) {
            $before[$lang->getLocale()] = SEOne::getConfigValue('title', null, $lang->getLocale());
        }

        $this->logIn($this->fixtures->admin());
        $form = $this->storeForm($this->editionLanguageId());
        $form->setValues([
            'seone_store_form_config[title]' => 'Tienda Weitech',
            'seone_store_form_config[description]' => 'Descripción de la tienda',
        ]);
        $this->client->submit($form);

        self::assertLessThan(400, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('edit_language_id='.$this->editionLanguageId(), (string) $this->client->getResponse()->headers->get('Location'));

        $this->forgetReadSettings();
        self::assertSame('Tienda Weitech', SEOne::getConfigValue('title', null, self::EDITION_LOCALE));
        self::assertSame('Descripción de la tienda', SEOne::getConfigValue('description', null, self::EDITION_LOCALE));
        foreach ($before as $locale => $title) {
            self::assertSame($title, SEOne::getConfigValue('title', null, $locale), $locale.' must not change');
        }
    }

    private function storeForm(int $editionLanguageId): Form
    {
        $crawler = $this->client->request('GET', self::SCREEN, ['edit_language_id' => $editionLanguageId]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $action = $this->getContainer()->get('router')->generate('seone_config_store_configuration');

        return $crawler
            ->filter('form')
            ->reduce(static fn (Crawler $candidate): bool => parse_url($candidate->attr('action') ?? '', \PHP_URL_PATH) === $action)
            ->first()
            ->form();
    }

    private function editionLanguageId(): int
    {
        $id = (int) LangQuery::create()->findOneByLocale(self::EDITION_LOCALE)?->getId();
        self::assertGreaterThan(0, $id, self::EDITION_LOCALE.' is installed');

        return $id;
    }

    private function logIn(Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    private function forgetReadSettings(): void
    {
        ModuleConfigQuery::resetConfigCache();
    }
}

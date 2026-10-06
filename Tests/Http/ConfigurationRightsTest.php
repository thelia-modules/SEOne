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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Propel\Runtime\Propel;
use SEOne\Model\RobotsQuery;
use SEOne\Model\SeoneQuery;
use SEOne\SEOne;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\Admin;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\Product;
use Thelia\Model\ProfileModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * Every form of the SEOne configuration screen is saved only by an administrator allowed to
 * update the module, and the SEO block of a record only by one allowed to update that record;
 * any other gets a refusal and changes nothing.
 */
final class ConfigurationRightsTest extends WebIntegrationTestCase
{
    private const string SCREEN = '/admin/module/SEOne';

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

        // The core keeps the parser of the last render in a static: after a refusal page, the
        // next request of the same process would render the module screen with it, empty.
        (new \ReflectionProperty(ParserResolver::class, 'currentParser'))->setValue(null, null);

        // Every read goes back to the database: what the requests saved, not a pooled copy.
        Propel::disableInstancePooling();
    }

    protected function tearDown(): void
    {
        Propel::enableInstancePooling();
        $this->injector->clear();

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, array<string, string>, callable(): ?string, string}>
     */
    public static function formProvider(): iterable
    {
        yield 'category limit' => [
            'seone_config_category_configuration',
            ['seone_category_form_config[category_limit]' => '7'],
            static fn (): ?string => SEOne::getConfigValue(SEOne::BETTER_SE0_LIMIT_CONFIG_KEY),
            '7',
        ];

        yield 'robots.txt' => [
            'seone_config_edit_robottxt',
            ['robottxt_configuration[robotContent]' => "User-agent: *\nDisallow: /changed"],
            static fn (): ?string => RobotsQuery::create()->orderById()->findOne()?->getRobotsContent(),
            "User-agent: *\nDisallow: /changed",
        ];
    }

    /**
     * @param array<string, string> $values
     * @param callable(): ?string   $readStoredValue
     */
    #[Test]
    #[DataProvider('formProvider')]
    public function anAdministratorWhoCannotUpdateTheModuleChangesNothing(string $route, array $values, callable $readStoredValue, string $submitted): void
    {
        $this->logIn($this->fixtures->admin());
        $form = $this->screenForm($route, $values);
        $before = $readStoredValue();

        $this->logIn($this->administratorWhoMayOnlySeeTheModule());
        $this->client->submit($form);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        $this->forgetReadSettings();
        self::assertSame($before, $readStoredValue());
    }

    /**
     * The same submission is saved for an administrator with every right: the refusal above
     * comes from the right, not from a form the test would have built wrong.
     *
     * @param array<string, string> $values
     * @param callable(): ?string   $readStoredValue
     */
    #[Test]
    #[DataProvider('formProvider')]
    public function anAdministratorWhoCanUpdateTheModuleSavesTheForm(string $route, array $values, callable $readStoredValue, string $submitted): void
    {
        $this->logIn($this->fixtures->admin());
        $this->client->submit($this->screenForm($route, $values));

        self::assertLessThan(400, $this->client->getResponse()->getStatusCode());
        $this->forgetReadSettings();
        self::assertSame($submitted, $readStoredValue());
    }

    #[Test]
    public function anAdministratorWhoCannotUpdateTheProductChangesNothingInItsSeoBlock(): void
    {
        $product = $this->product();
        $this->logIn($this->fixtures->admin());
        $form = $this->seoBlockForm($product);

        $this->logIn($this->fixtures->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW]]));
        $this->client->submit($form);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertNull(SeoneQuery::create()->filterByObjectType('product')->filterByObjectId($product->getId())->findOne());
    }

    #[Test]
    public function anAdministratorWhoCanUpdateTheProductSavesItsSeoBlock(): void
    {
        $product = $this->product();

        // The product screen itself is read by an administrator with every right; the save is
        // posted by one who may update products and nothing else.
        $this->logIn($this->fixtures->admin());
        $form = $this->seoBlockForm($product);
        $this->logIn($this->fixtures->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW, AccessManager::UPDATE]]));
        $this->client->submit($form);

        self::assertLessThan(400, $this->client->getResponse()->getStatusCode());
        $seo = SeoneQuery::create()->filterByObjectType('product')->filterByObjectId($product->getId())->findOne();
        self::assertNotNull($seo);
        self::assertSame('Typed heading', $seo->setLocale('en_US')->getH1());
    }

    private function seoBlockForm(Product $product): Form
    {
        $crawler = $this->client->request('GET', '/admin/products/update', ['product_id' => $product->getId(), 'current_tab' => 'seo']);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->filter('form[action*="/admin/module/seone/seo/save"]')->first()->form();
        $form->setValues(['seone_form[h1]' => 'Typed heading']);

        return $form;
    }

    private function product(): Product
    {
        return $this->fixtures->product(
            $this->fixtures->category(),
            $this->fixtures->taxRule(),
            $this->fixtures->currency(),
            ['title' => 'Chair', 'locale' => 'en_US'],
        );
    }

    /**
     * @param array<string, string> $values
     */
    private function screenForm(string $route, array $values): Form
    {
        $crawler = $this->client->request('GET', self::SCREEN);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $action = $this->getContainer()->get('router')->generate($route);
        $form = $crawler
            ->filter('form')
            ->reduce(static fn (Crawler $candidate): bool => parse_url($candidate->attr('action') ?? '', PHP_URL_PATH) === $action)
            ->first()
            ->form();
        $form->setValues($values);

        return $form;
    }

    private function administratorWhoMayOnlySeeTheModule(): Admin
    {
        $admin = $this->fixtures->restrictedAdmin([
            AdminResources::MODULE => [AccessManager::VIEW, AccessManager::UPDATE],
        ]);

        $view = new AccessManager(0);
        $view->build([AccessManager::VIEW]);

        (new ProfileModule())
            ->setProfileId($admin->getProfileId())
            ->setModuleId(SEOne::getModuleId())
            ->setAccess($view->getAccessValue())
            ->save();

        return $admin;
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

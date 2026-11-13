<?php

declare(strict_types=1);

namespace SEOne\Tests\Integration\Hook;

use PHPUnit\Framework\Attributes\Test;
use SEOne\Model\Seone;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The SEO card of the `tab-seo.bottom` hook shows the groups of fields the calling screen asks
 * for (`fields`), all of them otherwise; a group left out goes back as it is stored, so a save
 * in the card keeps it.
 */
final class SeoFormFieldsTest extends IntegrationTestCase
{
    private const string TYPE = 'seone-fields-test';

    private const int ID = 4242;

    protected function setUp(): void
    {
        parent::setUp();

        if ('default-twig' !== ConfigQuery::read('active-admin-template')) {
            self::markTestSkipped('The card is written for the default-twig back office, which this shop does not run.');
        }

        $lang = LangQuery::create()->findOneByByDefault(1);
        self::assertNotNull($lang);
        $session = new Session(new MockArraySessionStorage());
        $session->setAdminEditionLang($lang);
        $request = Request::create('http://localhost/admin');
        $request->setSession($session);
        $requestStack = $this->getService(RequestStack::class);
        while (null !== $requestStack->pop()) {
        }
        $requestStack->push($request);

        $seo = (new Seone())->setObjectType(self::TYPE)->setObjectId(self::ID);
        $seo->setLocale((string) $lang->getLocale())
            ->setNoindex(1)
            ->setNofollow(0)
            ->setH1('Titre H1')
            ->setJsonData('{"@type":"FAQPage"}')
            ->setMeshText1('Lien')
            ->setMeshUrl1('https://example.com/lien');
        $seo->save();
    }

    #[Test]
    public function withoutFieldsTheCardShowsThemAll(): void
    {
        $card = $this->card([]);

        foreach (['#seone-canonical', '#seone-noindex', '#seone-nofollow', '#seone-h1', '#seone-json'] as $field) {
            self::assertSame(1, $card->filter($field)->count(), $field);
        }
        self::assertSame(0, $card->filter('input[type="hidden"][name*="json_data"]')->count());
    }

    #[Test]
    public function aScreenAskingForTheStructuredDataGetsItAloneAndTheRestGoesBackAsStored(): void
    {
        $card = $this->card(['fields' => ['json_data']]);

        self::assertSame('{"@type":"FAQPage"}', trim($card->filter('textarea#seone-json')->text()));
        foreach (['#seone-canonical', '#seone-noindex', '#seone-nofollow', '#seone-h1'] as $field) {
            self::assertSame(0, $card->filter($field)->count(), $field.' is not shown');
        }

        $hidden = [];
        foreach ($card->filter('input[type="hidden"]') as $input) {
            $hidden[(string) $input->getAttribute('name')] = (string) $input->getAttribute('value');
        }
        self::assertSame('1', $hidden[$this->fieldName('noindex_checkbox')] ?? null, 'Stored noindex goes back.');
        self::assertArrayNotHasKey($this->fieldName('nofollow_checkbox'), $hidden, 'An unchecked box is not posted.');
        self::assertSame('Titre H1', $hidden[$this->fieldName('h1')] ?? null);
        self::assertSame('https://example.com/lien', $hidden[$this->fieldName('mesh_url_1')] ?? null);
        self::assertSame('', $hidden['canonical'] ?? null);
    }

    #[Test]
    public function unknownFieldsShowEverything(): void
    {
        self::assertSame(1, $this->card(['fields' => ['nothing-known']])->filter('#seone-noindex')->count());
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function card(array $arguments): Crawler
    {
        $event = new HookRenderEvent('tab-seo.bottom', ['id' => self::ID, 'type' => self::TYPE] + $arguments);
        $this->getService(EventDispatcherInterface::class)->dispatch($event, 'hook.'.TemplateDefinition::BACK_OFFICE.'.tab-seo.bottom');

        $html = $event->dump();
        self::assertStringContainsString('<form', $html, 'The card is rendered.');

        return new Crawler($html);
    }

    private function fieldName(string $field): string
    {
        return 'seone_form['.$field.']';
    }
}

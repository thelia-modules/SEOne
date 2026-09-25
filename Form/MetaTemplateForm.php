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

namespace SEOne\Form;

use SEOne\SEOne;
use SEOne\Service\MetaTemplate\MetaTemplateField;
use SEOne\Service\MetaTemplate\MetaTemplateRepository;
use SEOne\Service\MetaTemplate\MetaTemplateService;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

/**
 * One title and one description template per view, in the back-office edition language,
 * plus the two maximum lengths shared by every language.
 *
 * The views come from the resolvers: a module that declares a resolver gets its fields
 * here without touching this form. A template that names a variable no resolver provides
 * is refused, so a typo never reaches the pages.
 */
class MetaTemplateForm extends BaseForm
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly MetaTemplateService $metaTemplateService,
        private readonly MetaTemplateRepository $metaTemplateRepository,
    ) {
    }

    public static function getName(): string
    {
        return 'seone_meta_template_form';
    }

    public static function templateFieldName(string $view, MetaTemplateField $field): string
    {
        return $view.'_'.$field->value;
    }

    public static function maxLengthFieldName(MetaTemplateField $field): string
    {
        return 'max_length_'.$field->value;
    }

    protected function buildForm(): void
    {
        $locale = $this->getEditionLocale();

        foreach (array_keys($this->metaTemplateService->getResolvers()) as $view) {
            foreach (MetaTemplateField::cases() as $field) {
                $this->formBuilder->add(
                    self::templateFieldName($view, $field),
                    TextType::class,
                    [
                        'required' => false,
                        'label' => $this->translate('meta_template.field.'.$field->value),
                        'data' => $this->metaTemplateRepository->getTemplate($view, $field, $locale),
                        'constraints' => [
                            new Callback(
                                callback: fn (?string $value, ExecutionContextInterface $context) => $this->checkVariables($view, $value, $context),
                            ),
                        ],
                    ],
                );
            }
        }

        foreach (MetaTemplateField::cases() as $field) {
            $this->formBuilder->add(
                self::maxLengthFieldName($field),
                IntegerType::class,
                [
                    'required' => false,
                    'label' => $this->translate('meta_template.max_length.'.$field->value),
                    'data' => $this->metaTemplateRepository->getMaxLength($field),
                    'constraints' => [
                        new Range(min: 10, max: 500),
                    ],
                ],
            );
        }
    }

    private function checkVariables(string $view, ?string $value, ExecutionContextInterface $context): void
    {
        if (null === $value || '' === trim($value)) {
            return;
        }

        $unknownVariables = $this->metaTemplateService->findUnknownVariables($view, $value);

        if ([] === $unknownVariables) {
            return;
        }

        $context->addViolation($this->translate('meta_template.error.unknown_variable', [
            '%unknown%' => implode(', ', $unknownVariables),
            '%available%' => implode(', ', $this->metaTemplateService->getVariableNames($view)),
        ]));
    }

    /**
     * @param array<string, string> $parameters
     */
    private function translate(string $id, array $parameters = []): string
    {
        return (string) Translator::getInstance()->trans($id, $parameters, SEOne::DOMAIN_NAME);
    }

    /**
     * The same language the controller saves into: the one the back-office language selector
     * posts, then the administrator's own language. Reading in another language than the one
     * the save writes leaves the field empty after a successful save.
     */
    private function getEditionLocale(): string
    {
        $request = $this->requestStack->getMainRequest();

        $editionLanguageId = $request?->query->get('edit_language_id') ?? $request?->request->get('edit_language_id');

        if (null !== $editionLanguageId) {
            $editionLanguage = LangQuery::create()->findOneById($editionLanguageId);

            if (null !== $editionLanguage) {
                return (string) $editionLanguage->getLocale();
            }
        }

        $session = $request?->getSession();

        if ($session instanceof Session) {
            return (string) $session->getAdminLang()->getLocale();
        }

        return (string) Lang::getDefaultLanguage()->getLocale();
    }
}

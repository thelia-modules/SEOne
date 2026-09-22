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

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Model\ConfigQuery;

/**
 * The one entry point for meta templates: the SEO models ask it for a rendered title or
 * description, the configuration screen asks it which variables exist and which ones a
 * template uses by mistake.
 */
final class MetaTemplateService implements ResetInterface
{
    public const string STORE_NAME_VARIABLE = 'store_name';

    /**
     * @var array<string, VariableResolverInterface>|null keyed by view
     */
    private ?array $resolversByView = null;

    /**
     * @var array<string, array<string, string>> resolved values, keyed by "view|id|locale", for one request
     */
    private array $resolvedValues = [];

    /**
     * @param iterable<VariableResolverInterface> $resolvers
     */
    public function __construct(
        #[AutowireIterator(VariableResolverInterface::TAG)]
        private readonly iterable $resolvers,
        private readonly MetaTemplateRepository $repository,
        private readonly MetaTemplateRenderer $renderer,
    ) {
    }

    /**
     * @return array<string, VariableResolverInterface> keyed by view, in declaration order
     */
    public function getResolvers(): array
    {
        if (null === $this->resolversByView) {
            $this->resolversByView = [];
            foreach ($this->resolvers as $resolver) {
                $this->resolversByView[$resolver->getView()] = $resolver;
            }
        }

        return $this->resolversByView;
    }

    public function getResolver(string $view): ?VariableResolverInterface
    {
        return $this->getResolvers()[$view] ?? null;
    }

    /**
     * Every variable a template for this view may use: the resolver's, then the store name.
     *
     * @return list<string>
     */
    public function getVariableNames(string $view): array
    {
        $resolver = $this->getResolver($view);

        if (null === $resolver) {
            return [];
        }

        return array_values(array_unique([...$resolver->getVariableNames(), self::STORE_NAME_VARIABLE]));
    }

    /**
     * Variables used by $template that no resolver of $view provides.
     *
     * @return list<string>
     */
    public function findUnknownVariables(string $view, string $template): array
    {
        $known = $this->getVariableNames($view);

        return array_values(array_filter(
            MetaTemplateRenderer::extractVariableNames($template),
            static fn (string $name): bool => !\in_array($name, $known, true),
        ));
    }

    /**
     * The rendered meta value for this entity, or '' when no template is configured for the view
     * and language, when no resolver serves the view, when the entity does not exist, or when the
     * rendering comes out empty: the caller then applies its own fallback.
     */
    public function render(string $view, MetaTemplateField $field, int $id, string $locale): string
    {
        if ($id <= 0) {
            return '';
        }

        $template = $this->repository->getTemplate($view, $field, $locale);

        if ('' === $template) {
            return '';
        }

        $values = $this->resolveValues($view, $id, $locale);

        if ([] === $values) {
            return '';
        }

        return $this->renderer->render($template, $values, $this->repository->getMaxLength($field));
    }

    /**
     * Same as render(), for a template that is not saved yet (the configuration screen's check).
     */
    public function renderTemplate(string $view, MetaTemplateField $field, string $template, int $id, string $locale): string
    {
        $values = $this->resolveValues($view, $id, $locale);

        if ([] === $values) {
            return '';
        }

        return $this->renderer->render($template, $values, $this->repository->getMaxLength($field));
    }

    public function reset(): void
    {
        $this->resolvedValues = [];
    }

    /**
     * @return array<string, string>
     */
    private function resolveValues(string $view, int $id, string $locale): array
    {
        $key = $view.'|'.$id.'|'.$locale;

        if (\array_key_exists($key, $this->resolvedValues)) {
            return $this->resolvedValues[$key];
        }

        $resolver = $this->getResolver($view);

        if (null === $resolver) {
            return $this->resolvedValues[$key] = [];
        }

        $values = $resolver->resolve($id, $locale);

        if ([] !== $values) {
            $values[self::STORE_NAME_VARIABLE] = (string) (ConfigQuery::read('store_name') ?? '');
        }

        return $this->resolvedValues[$key] = $values;
    }
}

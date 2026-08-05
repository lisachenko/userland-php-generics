<?php

/**
 * Userland PHP Generics
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 */
declare(strict_types=1);

namespace Lisachenko\Generics;

use Lisachenko\Generics\Exception\TemplateException;
use Lisachenko\Generics\Exception\TypeArgumentException;
use Lisachenko\Generics\Naming\AngleBracketNameMangler;
use Lisachenko\Generics\Naming\NameMangler;
use Lisachenko\Generics\Runtime\EngineCapabilities;
use Lisachenko\Generics\Runtime\Monomorphizer;
use Lisachenko\Generics\Runtime\SpecializationCache;
use Lisachenko\Generics\Strategy\PlaceholderNameStrategy;
use Lisachenko\Generics\Strategy\SlotAttributeStrategy;
use Lisachenko\Generics\Strategy\SubstitutionPlan;
use Lisachenko\Generics\Strategy\SubstitutionStrategy;
use Lisachenko\Generics\Template\TemplateDefinition;
use Lisachenko\Generics\Template\TemplateRegistry;
use Lisachenko\Generics\Type\NestedTypeResolver;
use Lisachenko\Generics\Type\TypeArgumentResolver;

/**
 * Monomorphizes generic templates into real, engine-enforced classes
 *
 * This is the service; `Generic` is the static facade over a default instance of it. Inject
 * it directly when you need a different name mangler or a cache scoped to something other
 * than the process.
 *
 * Every validation happens before the engine is asked for anything, so a rejected call never
 * leaves a half-registered class behind - the same contract `ClassSpecializer` itself keeps.
 */
final class GenericFactory implements NestedTypeResolver
{
    /**
     * Nested generics are rare and shallow in practice; a limit turns a runaway recursion into
     * a named exception instead of a stack overflow.
     */
    private const DEFAULT_DEPTH_LIMIT = 8;

    /**
     * @var list<SubstitutionStrategy>
     */
    private readonly array $strategies;

    /**
     * Templates currently being specialized, innermost last, for cycle and depth reporting
     *
     * @var list<string>
     */
    private array $resolving = [];

    /**
     * @param list<SubstitutionStrategy>|null $strategies Ordered by preference; the first that
     *                                                    supports a template wins
     */
    public function __construct(
        private readonly TemplateRegistry $templates = new TemplateRegistry(),
        private readonly NameMangler $mangler = new AngleBracketNameMangler(),
        private readonly SpecializationCache $cache = new SpecializationCache(),
        private readonly Monomorphizer $monomorphizer = new Monomorphizer(),
        private readonly TypeArgumentResolver $arguments = new TypeArgumentResolver(),
        ?array $strategies = null,
        private readonly int $depthLimit = self::DEFAULT_DEPTH_LIMIT,
    ) {
        $this->strategies = $strategies ?? [new PlaceholderNameStrategy(), new SlotAttributeStrategy()];
    }

    /**
     * Returns the class name of `$templateName<...$typeArguments>`, materializing it on first use
     *
     * @param  class-string $templateName
     * @param  string       ...$typeArguments Type names in declaration order, e.g. `int`, `App\User`,
     *                                        or a nested `Box<int>`
     * @return class-string
     */
    public function specialize(string $templateName, string ...$typeArguments): string
    {
        if ($this->mangler->isMangled($templateName)) {
            throw TemplateException::alreadySpecialized($templateName);
        }

        $template = $this->templates->definitionOf($templateName);
        $bindings = $this->resolveBindings($template, array_values($typeArguments));
        $name     = $this->mangler->mangle($templateName, array_values($bindings));

        $known = $this->cache->lookup($name);
        if ($known !== null) {
            return $known;
        }

        $plan = new SubstitutionPlan();
        foreach ($this->strategies as $strategy) {
            $strategy->contribute($template, $bindings, $plan);
        }
        if ($plan->hasSlotSubstitutions() && !EngineCapabilities::supportsSlotSubstitution()) {
            throw TemplateException::slotSubstitutionUnavailable($templateName);
        }

        return $this->cache->remember(
            $this->monomorphizer->materialize($templateName, $name, $plan->toRequest()),
        );
    }

    /**
     * Specializes the template and instantiates it in one step
     *
     * @param class-string $templateName
     * @param list<string> $typeArguments
     */
    public function instantiate(string $templateName, array $typeArguments, mixed ...$constructorArguments): object
    {
        $specialized = $this->specialize($templateName, ...$typeArguments);

        return new $specialized(...$constructorArguments);
    }

    public function specializeNested(string $templateName, array $typeArguments): string
    {
        // Nesting is bounded by the brackets actually written, so no finite argument can loop
        // forever; the depth limit exists to keep a pathological one from exhausting the stack.
        if (count($this->resolving) >= $this->depthLimit) {
            throw TypeArgumentException::recursionLimit(
                $templateName,
                $this->depthLimit,
                $this->chain($templateName),
            );
        }

        return $this->specialize($templateName, ...$typeArguments);
    }

    public function cache(): SpecializationCache
    {
        return $this->cache;
    }

    /**
     * Validates arity and resolves each argument to the type name the engine will store
     *
     * @param  list<string>          $typeArguments
     * @return array<string, string> Type parameter name => concrete type name
     */
    private function resolveBindings(TemplateDefinition $template, array $typeArguments): array
    {
        if (count($typeArguments) !== $template->arity()) {
            throw TypeArgumentException::arityMismatch(
                $template->className,
                $template->arity(),
                count($typeArguments),
            );
        }

        $this->resolving[] = $template->className;

        try {
            $bindings = [];
            foreach ($template->parameters as $parameter) {
                $bindings[$parameter->name] = $this->arguments->resolve(
                    $template,
                    $parameter,
                    $typeArguments[$parameter->position],
                    $this,
                );
            }
        } finally {
            array_pop($this->resolving);
        }

        return $bindings;
    }

    private function chain(string $templateName): string
    {
        return implode(' -> ', [...$this->resolving, $templateName]);
    }
}

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
use Lisachenko\Generics\Runtime\SpecializationRegistry;
use Lisachenko\Generics\Runtime\TypeBinding;
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
        private readonly SpecializationRegistry $registry = new SpecializationRegistry(),
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

        $arguments = array_values($bindings);

        $known = $this->cache->lookup($name);
        if ($known !== null) {
            // Also recorded on the hit path: the template and its resolved arguments are in
            // hand right here, and a class adopted from another factory would otherwise stay
            // unexplained in the registry forever.
            return $this->record($templateName, $arguments, $known);
        }

        $plan = new SubstitutionPlan();
        foreach ($this->strategies as $strategy) {
            $strategy->contribute($template, $bindings, $plan);
        }
        if ($plan->hasSlotSubstitutions() && !EngineCapabilities::supportsSlotSubstitution()) {
            throw TemplateException::slotSubstitutionUnavailable($templateName);
        }

        return $this->record($templateName, $arguments, $this->cache->remember(
            $this->monomorphizer->materialize($templateName, $name, $plan->toRequest()),
        ));
    }

    /**
     * Materializes a known set of specializations in one call
     *
     * The documented shape for worker boot. Minting is not a hot-path operation - it costs on
     * the order of a hundred microseconds plus another hundred per own method, while asking
     * again costs about two - so a worker that knows its type arguments should pay for them
     * once, at start-up, rather than on the first request that happens to need one. See
     * docs/long-running.md for the budget and docs/benchmarks.md for the numbers behind it.
     *
     * ```php
     * Generic::warmUp([[Box::class, ['int']], [Box::class, [User::class]]]);
     * ```
     *
     * @param  list<array{0: class-string, 1: list<string>}> $specializations
     * @return list<class-string>                            In the order they were given
     */
    public function warmUp(array $specializations): array
    {
        $specialized = [];
        foreach ($specializations as [$templateName, $typeArguments]) {
            $specialized[] = $this->specialize($templateName, ...$typeArguments);
        }

        return $specialized;
    }

    /**
     * Forgets every memoized specialization without touching the class table
     *
     * The classes stay registered because they are engine state rather than ours, so a later
     * `specialize()` for the same arguments adopts the existing class instead of building a
     * second one. Parsed template definitions are kept: they are pure reflection over a
     * declaration that cannot change while the process runs.
     */
    public function reset(): void
    {
        $this->cache->forget();
        $this->registry->forget();
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

    public function registry(): SpecializationRegistry
    {
        return $this->registry;
    }

    /**
     * The mangler this factory names specializations with
     *
     * Exposed for `GenericAutoloader`, which has to recognise and take apart a name before it
     * can decide whether the name is one this factory could have produced.
     */
    public function mangler(): NameMangler
    {
        return $this->mangler;
    }

    /**
     * Whether the value is a specialization, optionally of one particular template
     *
     * The answer `instanceof` cannot give. A specialization is a *sibling* of its template, so
     * `$box instanceof Box` is false and always will be; this asks the registry instead, and
     * falls back to reading the name apart - which is what keeps it working for an instance
     * minted by a different factory.
     *
     * @param class-string|null $templateName
     */
    public function isSpecialization(object|string $value, ?string $templateName = null): bool
    {
        $binding = $this->bindingRecordOf($this->classNameOf($value));
        if ($binding === null) {
            return false;
        }

        return $templateName === null || $binding->templateName === $templateName;
    }

    /**
     * The template a specialization was made from, or null if this is not a specialization
     *
     * @return class-string|null
     */
    public function templateOf(object|string $value): ?string
    {
        return $this->bindingRecordOf($this->classNameOf($value))?->templateName;
    }

    /**
     * The concrete type arguments a specialization was made for, in declaration order
     *
     * @return list<string>|null
     */
    public function bindingOf(object|string $value): ?array
    {
        return $this->bindingRecordOf($this->classNameOf($value))?->typeArguments;
    }

    /**
     * What is known about a specialized name: recorded first, parsed only as a fallback
     *
     * The registry is exact - it was written when the class was made - but it only covers this
     * process. Parsing covers everything else, at the mangler's accuracy: for the default
     * angle-bracket names that is exact too, and for `IdentifierSafeNameMangler` it is the
     * best-effort that mangler documents on itself.
     */
    private function bindingRecordOf(string $className): ?TypeBinding
    {
        $recorded = $this->registry->bindingFor($className);
        if ($recorded !== null) {
            return $recorded;
        }

        $parsed = $this->mangler->parse($className);
        if ($parsed === null) {
            return null;
        }

        /** @var class-string $className */
        return TypeBinding::of($parsed->templateName, $parsed->typeArguments, $className);
    }

    /**
     * @param  class-string        $templateName
     * @param  list<string>        $typeArguments
     * @param  class-string        $specializedName
     * @return class-string
     */
    private function record(string $templateName, array $typeArguments, string $specializedName): string
    {
        $this->registry->record(TypeBinding::of($templateName, $typeArguments, $specializedName));

        return $specializedName;
    }

    private function classNameOf(object|string $value): string
    {
        return is_object($value) ? $value::class : $value;
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

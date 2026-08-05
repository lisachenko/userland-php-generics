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
use Lisachenko\Generics\Runtime\Monomorphizer;
use Lisachenko\Generics\Runtime\SpecializationCache;
use Lisachenko\Generics\Strategy\PlaceholderNameStrategy;
use Lisachenko\Generics\Strategy\SubstitutionStrategy;
use Lisachenko\Generics\Template\TemplateDefinition;
use Lisachenko\Generics\Template\TemplateRegistry;
use Lisachenko\Generics\Type\BuiltinTypes;

/**
 * Monomorphizes generic templates into real, engine-enforced classes
 *
 * This is the service; `Generic` is the static facade over a default instance of it. Inject
 * it directly when you need a different name mangler or want a cache scoped to something
 * other than the process.
 *
 * Every validation happens before the engine is asked for anything, so a rejected call never
 * leaves a half-registered class behind - the same contract `ClassSpecializer` itself keeps.
 */
final class GenericFactory
{
    /**
     * @var list<SubstitutionStrategy>
     */
    private readonly array $strategies;

    /**
     * @param list<SubstitutionStrategy>|null $strategies Ordered by preference; the first that
     *                                                    supports a template wins
     */
    public function __construct(
        private readonly TemplateRegistry $templates = new TemplateRegistry(),
        private readonly NameMangler $mangler = new AngleBracketNameMangler(),
        private readonly SpecializationCache $cache = new SpecializationCache(),
        private readonly Monomorphizer $monomorphizer = new Monomorphizer(),
        ?array $strategies = null,
    ) {
        $this->strategies = $strategies ?? [new PlaceholderNameStrategy()];
    }

    /**
     * Returns the class name of `$templateName<...$typeArguments>`, materializing it on first use
     *
     * @param  class-string  $templateName
     * @param  string        ...$typeArguments Builtin type names or class names, in declaration order
     * @return class-string
     */
    public function specialize(string $templateName, string ...$typeArguments): string
    {
        if ($this->mangler->isMangled($templateName)) {
            throw TemplateException::alreadySpecialized($templateName);
        }

        $template  = $this->templates->definitionOf($templateName);
        $arguments = $this->normalizeArguments($template, array_values($typeArguments));
        $name      = $this->mangler->mangle($templateName, $arguments);

        $known = $this->cache->lookup($name);
        if ($known !== null) {
            return $known;
        }

        $request = $this->strategyFor($template)->buildRequest($template, $this->bind($template, $arguments));

        return $this->cache->remember($this->monomorphizer->materialize($templateName, $name, $request));
    }

    /**
     * Specializes the template and instantiates it in one step
     *
     * @param class-string  $templateName
     * @param list<string>  $typeArguments
     */
    public function instantiate(string $templateName, array $typeArguments, mixed ...$constructorArguments): object
    {
        $specialized = $this->specialize($templateName, ...$typeArguments);

        return new $specialized(...$constructorArguments);
    }

    public function cache(): SpecializationCache
    {
        return $this->cache;
    }

    /**
     * Validates arity and normalizes every argument to the spelling the engine will store
     *
     * @param  list<string> $typeArguments
     * @return list<string>
     */
    private function normalizeArguments(TemplateDefinition $template, array $typeArguments): array
    {
        if (count($typeArguments) !== $template->arity()) {
            throw TypeArgumentException::arityMismatch(
                $template->className,
                $template->arity(),
                count($typeArguments),
            );
        }

        $normalized = [];
        foreach ($typeArguments as $position => $argument) {
            $normalized[] = $this->normalizeArgument($template, $argument, $position + 1);
        }

        return $normalized;
    }

    private function normalizeArgument(TemplateDefinition $template, string $argument, int $position): string
    {
        $typeName = ltrim(trim($argument), '\\');
        if ($typeName === '') {
            throw TypeArgumentException::emptyArgument($template->className, $position);
        }

        if (BuiltinTypes::isSubstitutable($typeName)) {
            return strtolower($typeName);
        }

        $rejection = BuiltinTypes::rejectionReasonFor($typeName);
        if ($rejection !== null) {
            throw TypeArgumentException::unsupportedType($template->className, $typeName, $rejection);
        }

        if (str_contains($typeName, '|') || str_contains($typeName, '&')) {
            throw TypeArgumentException::unsupportedType(
                $template->className,
                $typeName,
                'the engine cannot substitute a union or intersection type into a declaration slot',
            );
        }

        // A specialized name registered earlier is a perfectly ordinary class here, which is
        // what makes Box::of(Box::of('int')) work.
        if (!class_exists($typeName) && !interface_exists($typeName) && !enum_exists($typeName)) {
            throw TypeArgumentException::unknownType($template->className, $typeName);
        }

        return $typeName;
    }

    /**
     * @param  list<string>          $arguments
     * @return array<string, string> Type parameter name => concrete type name
     */
    private function bind(TemplateDefinition $template, array $arguments): array
    {
        $bindings = [];
        foreach ($template->parameters as $parameter) {
            $bindings[$parameter->name] = $arguments[$parameter->position];
        }

        return $bindings;
    }

    private function strategyFor(TemplateDefinition $template): SubstitutionStrategy
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($template)) {
                return $strategy;
            }
        }

        throw TemplateException::noSubstitutionStrategy($template->className);
    }
}

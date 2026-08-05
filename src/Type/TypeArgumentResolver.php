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

namespace Lisachenko\Generics\Type;

use Lisachenko\Generics\Exception\TypeArgumentException;
use Lisachenko\Generics\Template\TemplateDefinition;
use Lisachenko\Generics\Template\TemplateParameterDefinition;

/**
 * Turns a written type argument into the exact type name the engine will store
 *
 * Everything this class rejects, it rejects before the engine is asked for anything: an
 * argument that cannot become a `zend_type` must fail as a clear exception at the call site,
 * never as a surprising TypeError from a half-built class much later.
 */
final class TypeArgumentResolver
{
    public function __construct(private readonly TypeArgumentParser $parser = new TypeArgumentParser()) {}

    /**
     * @param string $rawArgument The argument as written, e.g. `int`, `App\User`, `Box<int>`
     */
    public function resolve(
        TemplateDefinition $template,
        TemplateParameterDefinition $parameter,
        string $rawArgument,
        NestedTypeResolver $nested,
    ): string {
        $argument = $this->parser->parse($template->className, $rawArgument);

        if ($argument->nullable) {
            // The name-keyed substitution path preserves whatever nullability the template
            // declared and cannot introduce MAY_BE_NULL, so `?X` here would quietly produce a
            // non-nullable slot. Refusing is the only honest answer; declare `?T` instead.
            throw TypeArgumentException::nullabilityNotExpressible($template->className, $argument->toString());
        }

        $typeName = $argument->isGeneric()
            ? $nested->specializeNested($this->assertClassLike($template, $argument), $this->render($argument->arguments))
            : $this->resolvePlainType($template, $argument);

        $this->assertSatisfiesBound($template, $parameter, $typeName, $argument->toString());

        return $typeName;
    }

    private function resolvePlainType(TemplateDefinition $template, TypeArgument $argument): string
    {
        $typeName = $argument->name;

        if (BuiltinTypes::isSubstitutable($typeName)) {
            return strtolower($typeName);
        }

        $rejection = BuiltinTypes::rejectionReasonFor($typeName);
        if ($rejection !== null) {
            throw TypeArgumentException::unsupportedType($template->className, $typeName, $rejection);
        }

        // An already-registered specialization is an ordinary class here, which is what lets a
        // specialized name be passed straight back in as a type argument.
        if (!class_exists($typeName) && !interface_exists($typeName) && !enum_exists($typeName)) {
            throw TypeArgumentException::unknownType($template->className, $typeName);
        }

        return $typeName;
    }

    /**
     * @return class-string
     */
    private function assertClassLike(TemplateDefinition $template, TypeArgument $argument): string
    {
        if (!class_exists($argument->name)) {
            throw TypeArgumentException::unknownNestedTemplate($template->className, $argument->name);
        }

        return $argument->name;
    }

    private function assertSatisfiesBound(
        TemplateDefinition $template,
        TemplateParameterDefinition $parameter,
        string $typeName,
        string $written,
    ): void {
        $bound = $parameter->bound;
        if ($bound === null) {
            return;
        }

        $normalizedBound = ltrim($bound, '\\');
        if (BuiltinTypes::isSubstitutable($normalizedBound)) {
            $satisfied = strtolower($normalizedBound) === 'object'
                ? !BuiltinTypes::isSubstitutable($typeName)
                : strtolower($normalizedBound) === strtolower($typeName);
        } else {
            $satisfied = !BuiltinTypes::isSubstitutable($typeName) && is_a($typeName, $normalizedBound, true);
        }

        if (!$satisfied) {
            throw TypeArgumentException::boundViolation(
                $template->className,
                $parameter->name,
                $normalizedBound,
                $written,
            );
        }
    }

    /**
     * @param  list<TypeArgument> $arguments
     * @return list<string>
     */
    private function render(array $arguments): array
    {
        return array_map(static fn(TypeArgument $argument): string => $argument->toString(), $arguments);
    }
}

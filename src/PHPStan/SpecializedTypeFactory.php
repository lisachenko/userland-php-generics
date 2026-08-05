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

namespace Lisachenko\Generics\PHPStan;

use Lisachenko\Generics\Type\BuiltinTypes;
use PhpParser\Node\Arg;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\GenericClassStringType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Type;
use Throwable;

/**
 * Turns the literal type-argument strings at a call site into the specialization's type
 *
 * Shared by all three return-type extensions, because `Box::of('int')`,
 * `Generic::specialize(Box::class, 'int')` and `Generic::new(Box::class, ['int'])` differ only
 * in where the strings are written.
 *
 * **Everything here degrades to null rather than guessing.** A return-type extension that
 * returns null leaves PHPStan with the declared return type, which is merely unhelpful; one
 * that returns a confidently wrong type makes the analyser lie. Anything not resolvable at
 * analysis time - a variable argument, an unknown class, an arity that does not match the
 * template - takes the null path.
 */
final class SpecializedTypeFactory
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly TypeStringResolver $typeStringResolver,
    ) {}

    /**
     * `class-string<Box<int>>` for the name-returning entry points
     *
     * @param list<string> $typeArguments
     */
    public function classString(string $templateName, array $typeArguments): ?Type
    {
        $object = $this->object($templateName, $typeArguments);

        return $object === null ? null : new GenericClassStringType($object);
    }

    /**
     * `Box<int>` for the entry point that returns an instance
     *
     * @param list<string> $typeArguments
     */
    public function object(string $templateName, array $typeArguments): ?GenericObjectType
    {
        $class = TemplateReflection::find($this->reflectionProvider, $templateName);
        if ($class === null) {
            return null;
        }

        // Arity is checked against the `@template` tags rather than the attributes, because
        // those are what PHPStan will resolve the resulting generic type against. A mismatch
        // between the two is a separate diagnosis, and TemplateParameterConsistencyRule owns it
        if (count($class->getTemplateTags()) !== count($typeArguments)) {
            return null;
        }

        $resolved = [];
        foreach ($typeArguments as $argument) {
            $type = $this->resolve($argument);
            if ($type === null) {
                return null;
            }
            $resolved[] = $type;
        }

        return new GenericObjectType($class->getName(), $resolved);
    }

    /**
     * Reads a variadic run of arguments as literal strings
     *
     * @param  list<Arg>         $arguments
     * @return list<string>|null Null as soon as one of them is not a single literal string
     */
    public function literalStrings(array $arguments, Scope $scope): ?array
    {
        $strings = [];
        foreach ($arguments as $argument) {
            if ($argument->unpack) {
                // A spread hides how many arguments there are, let alone their values
                return null;
            }
            $constants = $scope->getType($argument->value)->getConstantStrings();
            if (count($constants) !== 1) {
                return null;
            }
            $strings[] = $constants[0]->getValue();
        }

        return $strings;
    }

    /**
     * Reads a literal `['int', 'string']` argument
     *
     * @return list<string>|null
     */
    public function literalStringList(Arg $argument, Scope $scope): ?array
    {
        $arrays = $scope->getType($argument->value)->getConstantArrays();
        if (count($arrays) !== 1) {
            return null;
        }

        $strings = [];
        foreach ($arrays[0]->getValueTypes() as $valueType) {
            $constants = $valueType->getConstantStrings();
            if (count($constants) !== 1) {
                return null;
            }
            $strings[] = $constants[0]->getValue();
        }

        return $strings;
    }

    /**
     * Resolves one type argument through PHPStan's own type parser
     *
     * The type-argument grammar was deliberately made a subset of PHPStan's so this could be a
     * delegation rather than a second parser that has to be kept in step. A nested `Box<int>`
     * argument therefore resolves for free.
     */
    private function resolve(string $argument): ?Type
    {
        $trimmed = trim($argument);
        if ($trimmed === '' || BuiltinTypes::rejectionReasonFor($trimmed) !== null) {
            return null;
        }

        try {
            return $this->typeStringResolver->resolve($trimmed);
        } catch (Throwable) {
            // Malformed at analysis time is the runtime's problem to report; the extension's
            // only job is to not invent a type for it
            return null;
        }
    }
}

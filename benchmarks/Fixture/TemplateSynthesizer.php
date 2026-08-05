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

namespace Lisachenko\Generics\Benchmark\Fixture;

use Lisachenko\Generics\Attribute\Of;
use Lisachenko\Generics\Attribute\TemplateParameter;
use Lisachenko\Generics\GenericObject;
use Lisachenko\Generics\Type\BuiltinTypes;

/**
 * Builds templates, type arguments and hand-written equivalents to order
 *
 * Everything is `eval()`ed into one namespace with a monotonic counter, so a scenario can ask
 * for as many distinct classes as it likes without ever colliding with a previous run.
 *
 * Bodies are deliberately made of `$acc += <n>;` statements: each one compiles to a single
 * `ASSIGN_OP` whose right operand is an `IS_CONST` literal, which makes the statement count a
 * good proxy for the opcode count *and* gives the opcode-relocation path the busiest input it
 * will ever see.
 */
final class TemplateSynthesizer
{
    /**
     * Where every synthesized class lands
     *
     * A namespace matters here: the placeholder form keys on a type's short name, so `?T`
     * inside this namespace is the fictional `...\Generated\T` and reads back as `T`.
     */
    public const NAMESPACE = 'Lisachenko\\Generics\\Benchmark\\Generated';

    /**
     * Process-wide, not per-instance: a class name is claimed for the whole request, so two
     * synthesizers minting `Template_1` is a fatal error rather than a collision to resolve
     */
    private static int $counter = 0;

    /**
     * Synthesizes a generic template of the requested shape
     *
     * @return class-string
     */
    public function template(TemplateShape $shape): string
    {
        $shortName = sprintf('Template_%d', ++self::$counter);

        $body = [];
        for ($index = 0; $index < $shape->properties; ++$index) {
            $body[] = match ($shape->propertySlot) {
                PropertySlot::Placeholder => sprintf('    private ?T $slot%d = null;', $index),
                PropertySlot::Attribute   => sprintf(
                    "    #[\\%s('T')]\n    private mixed \$slot%d = null;",
                    Of::class,
                    $index,
                ),
            };
        }
        for ($index = 0; $index < $shape->methods; ++$index) {
            $body[] = $this->method($shape, $index, $this->parameterDeclaration($shape->slot));
        }

        $this->declare(sprintf(
            "#[\\%s('T')]\nclass %s%s\n{\n%s\n}",
            TemplateParameter::class,
            $shortName,
            $this->interfaces($shape, [GenericObject::class]),
            implode("\n\n", $body),
        ));

        /** @var class-string */
        return self::NAMESPACE . '\\' . $shortName;
    }

    /**
     * Synthesizes the same shape with a concrete type written in, the way a codegen library
     * would emit it
     *
     * This is the honest comparison for the memory scenario: identical surface, produced by a
     * full compile instead of a class-entry copy.
     *
     * @param  string       $typeArgument A class name or a builtin type name
     * @return class-string
     */
    public function concrete(TemplateShape $shape, string $typeArgument): string
    {
        $shortName = sprintf('Concrete_%d', ++self::$counter);
        $type      = self::typeExpression($typeArgument);

        $body = [];
        for ($index = 0; $index < $shape->properties; ++$index) {
            $body[] = sprintf('    private ?%s $slot%d = null;', $type, $index);
        }
        for ($index = 0; $index < $shape->methods; ++$index) {
            // The parameter mirrors the template's: with MethodSlot::PropertyOnly the signature
            // is deliberately left unchecked so only the property write is being compared
            $body[] = $this->method(
                $shape,
                $index,
                $shape->slot === MethodSlot::PropertyOnly ? 'mixed $value' : sprintf('%s $value', $type),
            );
        }

        $this->declare(sprintf(
            "class %s%s\n{\n%s\n}",
            $shortName,
            $this->interfaces($shape, []),
            implode("\n\n", $body),
        ));

        /** @var class-string */
        return self::NAMESPACE . '\\' . $shortName;
    }

    /**
     * Synthesizes the same shape with no type discipline at all
     *
     * The control for the dispatch scenario: identical bodies, `mixed` everywhere, so the
     * engine has nothing to check. Whatever it beats a specialization by is the price of the
     * check itself.
     *
     * @return class-string
     */
    public function unchecked(TemplateShape $shape): string
    {
        $shortName = sprintf('Unchecked_%d', ++self::$counter);

        $body = [];
        for ($index = 0; $index < $shape->properties; ++$index) {
            $body[] = sprintf('    private mixed $slot%d = null;', $index);
        }
        for ($index = 0; $index < $shape->methods; ++$index) {
            $body[] = $this->method($shape, $index, 'mixed $value');
        }

        $this->declare(sprintf(
            "class %s implements \\%s\n{\n%s\n}",
            $shortName,
            BenchmarkSubject::class,
            implode("\n\n", $body),
        ));

        /** @var class-string */
        return self::NAMESPACE . '\\' . $shortName;
    }

    /**
     * Synthesizes empty classes usable as distinct type arguments
     *
     * Callers materialize these *before* a measurement starts: N specializations need N
     * distinct arguments, and the arguments themselves are not what is being measured.
     *
     * @return list<class-string>
     */
    public function typeArguments(int $count): array
    {
        $names = [];
        for ($index = 0; $index < $count; ++$index) {
            $names[] = $this->typeArgument();
        }

        return $names;
    }

    /**
     * Synthesizes a single empty class usable as a type argument
     *
     * @return class-string
     */
    public function typeArgument(): string
    {
        $shortName = sprintf('Argument_%d', ++self::$counter);
        $this->declare(sprintf('final class %s {}', $shortName));

        /** @var class-string */
        return self::NAMESPACE . '\\' . $shortName;
    }

    /**
     * Synthesizes one empty class whose name is padded to a given length
     *
     * Length is the independent variable of the name-resolution measurement, so it has to be
     * controllable on its own without changing anything else about the class.
     *
     * @return class-string
     */
    public function paddedTypeArgument(int $length): string
    {
        $prefix    = sprintf('Padded_%d_', ++self::$counter);
        $shortName = $prefix . str_repeat('x', max(0, $length - strlen($prefix)));

        $this->declare(sprintf('final class %s {}', $shortName));

        /** @var class-string */
        return self::NAMESPACE . '\\' . $shortName;
    }

    /**
     * The `implements` list for a synthesized class
     *
     * `BenchmarkSubject` is only added when the shape leaves `m0()` taking `mixed`. Adding it
     * unconditionally would be a declaration error rather than a looser check: a `T $value`
     * parameter is narrower than the interface's, which PHP refuses outright.
     *
     * @param list<class-string> $always
     */
    private function interfaces(TemplateShape $shape, array $always): string
    {
        $names = $always;
        if ($shape->slot === MethodSlot::PropertyOnly) {
            $names[] = BenchmarkSubject::class;
        }
        if ($names === []) {
            return '';
        }

        return ' implements ' . implode(', ', array_map(static fn(string $name): string => '\\' . $name, $names));
    }

    /**
     * Writes a type name the way source code has to spell it
     *
     * A class name is fully qualified so the generated namespace cannot capture it; a builtin
     * must not be, or `\int` would be read as a class.
     */
    public static function typeExpression(string $typeName): string
    {
        return BuiltinTypes::isSubstitutable($typeName) ? $typeName : '\\' . ltrim($typeName, '\\');
    }

    /**
     * The parameter declaration that produces the requested kind of substituted slot
     */
    private function parameterDeclaration(MethodSlot $slot): string
    {
        return match ($slot) {
            // Not a slot at all: nothing to substitute, so arg_info and opcodes both stay shared
            MethodSlot::None             => 'int $value',
            MethodSlot::PropertyOnly     => 'mixed $value',
            MethodSlot::ClassParameter   => 'T $value',
            MethodSlot::BuiltinParameter => sprintf('#[\\%s(\'T\')] mixed $value', Of::class),
        };
    }

    private function method(TemplateShape $shape, int $index, string $parameter): string
    {
        $statements = ['        $acc = 0;'];
        for ($step = 1; $step <= $shape->statements; ++$step) {
            // A distinct increment per statement forces a distinct literal, so the literals
            // table grows with K just like the opcode array does
            $statements[] = sprintf('        $acc += %d;', $step);
        }

        // Only emit the property write where the substituted types line up: with MethodSlot::None
        // the parameter stays `int` while the property becomes the type argument.
        if ($shape->slot !== MethodSlot::None) {
            $statements[] = '        $this->slot0 = $value;';
        }
        $statements[] = '        return $acc;';

        return sprintf(
            "    public function m%d(%s): int\n    {\n%s\n    }",
            $index,
            $parameter,
            implode("\n", $statements),
        );
    }

    private function declare(string $source): void
    {
        eval(sprintf('namespace %s; %s', self::NAMESPACE, $source));
    }
}

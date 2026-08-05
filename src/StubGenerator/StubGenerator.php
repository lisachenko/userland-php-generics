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

namespace Lisachenko\Generics\StubGenerator;

use Lisachenko\Generics\Template\SlotDefinition;
use Lisachenko\Generics\Template\SlotForm;
use Lisachenko\Generics\Template\SlotKind;
use Lisachenko\Generics\Template\TemplateDefinition;
use Lisachenko\Generics\Template\TemplateParameterDefinition;
use Lisachenko\Generics\Template\TemplateParser;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

/**
 * Writes the PHPStan stub a placeholder-form template needs
 *
 * A placeholder-form template declares the type parameter as its *native* type - `private ?T
 * $value` - which is exactly what the engine keys substitution on and exactly the opposite of
 * what an analyser needs: PHPStan resolves the native `T` to an object type and lets it beat
 * any `@param T`, so every call site on a specialization reads `expects App\T, int given`.
 *
 * A stub file **replaces** the declaration for analysis, which makes it the one mechanism that
 * can describe the class the way it behaves at run time: `mixed` where the fiction used to be,
 * with the equivalent doc tags alongside. The shipped return-type extension then narrows
 * `of()` to the precise specialization.
 *
 * Templates are read through `TemplateParser`, so the generator accepts exactly what the
 * runtime accepts and rejects the same things for the same reasons.
 */
final class StubGenerator
{
    public function __construct(private readonly TemplateParser $parser = new TemplateParser()) {}

    /**
     * Renders the stub for one template
     *
     * **One class per file, always.** PHPStan indexes only the first class declaration in a
     * stub and silently ignores the rest, so a generator that packed several together would
     * produce a file that mostly does nothing.
     *
     * @param class-string $className
     */
    public function generate(string $className): GeneratedStub
    {
        $definition = $this->parser->parse($className);
        $reflection = new ReflectionClass($className);

        $body = [];
        foreach ($reflection->getProperties() as $property) {
            if ($this->isOwnedBy($reflection, $property)) {
                $body[] = $this->property($property, $definition);
            }
        }
        foreach ($reflection->getMethods() as $method) {
            if ($this->isOwnedBy($reflection, $method) && !$method->isStatic()) {
                $body[] = $this->method($method, $definition);
            }
        }

        // of() is written out rather than inherited from GenericTemplate: stub files are
        // reflected before the analysed paths are indexed, so a stub cannot name the library's
        // own traits or interfaces
        $body[] = $this->constructorOfClasses($reflection->getShortName(), $definition);

        return new GeneratedStub(
            $className,
            $reflection->getNamespaceName(),
            sprintf(
                "%s\n%sclass %s\n{\n%s\n}\n",
                $this->classDocBlock($definition),
                $reflection->isFinal() ? 'final ' : '',
                $reflection->getShortName(),
                implode("\n\n", $body),
            ),
            $this->placeholderNames($definition),
        );
    }

    /**
     * The fictional placeholder class names a template's declarations refer to
     *
     * They have to be declared somewhere for PHPStan to resolve them at all, which is what the
     * companion `scanFiles` file is for - unlike the stubs, that one holds every name at once
     * because it declares nothing PHPStan needs to index as a real class.
     *
     * @return list<string>
     */
    private function placeholderNames(TemplateDefinition $definition): array
    {
        $names = [];
        foreach ($definition->slots as $slot) {
            if ($slot->form === SlotForm::Placeholder && $slot->declaredTypeName !== null) {
                $names[$slot->declaredTypeName] = true;
            }
        }

        return array_keys($names);
    }

    private function classDocBlock(TemplateDefinition $definition): string
    {
        $lines = ['/**'];
        foreach ($definition->parameters as $parameter) {
            // One tag per line: PHPStan reads only the first @template on a line, and a
            // template whose arity is misread analyses as a different type than it is
            $lines[] = $parameter->bound === null
                ? sprintf(' * @template %s', $parameter->name)
                : sprintf(' * @template %s of \\%s', $parameter->name, ltrim($parameter->bound, '\\'));
        }
        $lines[] = ' */';

        return implode("\n", $lines);
    }

    private function property(ReflectionProperty $property, TemplateDefinition $definition): string
    {
        $slot = $this->slotFor($definition, SlotKind::Property, $property->getName());
        $type = $slot === null ? $this->nativeType($property->getType()) : 'mixed';

        $docBlock = $slot === null
            ? ''
            : sprintf("    /** @var %s */\n", $this->docType($slot, $property->getType()));

        // A typed property with no default is uninitialized, which is a different thing from
        // one defaulting to null - and PHPStan is right to treat them differently
        $default = $property->hasDefaultValue()
            // var_export() writes NULL/TRUE/FALSE in capitals, which no PHP style guide wants
            ? sprintf(' = %s', str_replace(['NULL', 'TRUE', 'FALSE'], ['null', 'true', 'false'], var_export($property->getDefaultValue(), true)))
            : '';

        return sprintf(
            '%s    %s %s $%s%s;',
            $docBlock,
            $property->isPublic() ? 'public' : ($property->isProtected() ? 'protected' : 'private'),
            $type,
            $property->getName(),
            $default,
        );
    }

    private function method(ReflectionMethod $method, TemplateDefinition $definition): string
    {
        $tags       = [];
        $parameters = [];
        foreach ($method->getParameters() as $index => $parameter) {
            $slot = $this->parameterSlot($definition, $method->getName(), $index);
            if ($slot !== null) {
                $tags[] = sprintf(
                    '     * @param %s $%s',
                    $this->docType($slot, $parameter->getType()),
                    $parameter->getName(),
                );
            }
            $parameters[] = $this->parameter($parameter, $slot);
        }

        $returnSlot = $this->slotFor($definition, SlotKind::ReturnType, $method->getName());
        if ($returnSlot !== null) {
            $tags[] = sprintf('     * @return %s', $this->docType($returnSlot, $method->getReturnType()));
        }
        $returnType = $returnSlot === null ? $this->nativeType($method->getReturnType()) : 'mixed';

        return sprintf(
            '%s    public function %s(%s)%s {}',
            $tags === [] ? '' : sprintf("    /**\n%s\n     */\n", implode("\n", $tags)),
            $method->getName(),
            implode(', ', $parameters),
            $returnType === '' ? '' : ': ' . $returnType,
        );
    }

    private function parameter(ReflectionParameter $parameter, ?SlotDefinition $slot): string
    {
        $type = $slot === null ? $this->nativeType($parameter->getType()) : 'mixed';

        return trim(sprintf(
            '%s %s$%s',
            $type,
            $parameter->isVariadic() ? '...' : '',
            $parameter->getName(),
        ));
    }

    /**
     * `of()` declared inline, returning the class-string the extension then narrows
     *
     * The type parameters are spelled out rather than left off: the stub class *is* generic,
     * so a bare `class-string<Box>` is an incomplete generic type and PHPStan says so. This is
     * only the fallback - the shipped return-type extension replaces it with the precise
     * specialization whenever the arguments are literals.
     */
    private function constructorOfClasses(string $shortName, TemplateDefinition $definition): string
    {
        $parameters = array_map(
            static fn(TemplateParameterDefinition $parameter): string => $parameter->name,
            $definition->parameters,
        );

        return sprintf(
            "    /**\n     * @return class-string<%s%s>\n     */\n"
            . '    public static function of(string ...$typeArguments): string {}',
            $shortName,
            $parameters === [] ? '' : sprintf('<%s>', implode(', ', $parameters)),
        );
    }

    /**
     * The doc type that carries the meaning the native `mixed` just gave up
     *
     * Nullability has to be read from the declaration as well as from the slot, because the
     * two forms record it in different places: the attribute form puts it on the slot, while
     * the placeholder form spells it in the native type itself (`?T`), which is precisely the
     * declaration the stub is about to replace.
     */
    private function docType(SlotDefinition $slot, ?\ReflectionType $declared): string
    {
        $nullable = $slot->nullable
            || ($declared instanceof ReflectionNamedType
                && $declared->allowsNull()
                && $declared->getName() !== 'mixed');

        return $nullable ? sprintf('%s|null', $slot->templateParameter) : $slot->templateParameter;
    }

    private function nativeType(?\ReflectionType $type): string
    {
        if ($type === null) {
            return '';
        }
        if (!$type instanceof ReflectionNamedType) {
            return (string) $type;
        }

        $name = $type->isBuiltin() ? $type->getName() : '\\' . ltrim($type->getName(), '\\');

        return $type->allowsNull() && $type->getName() !== 'mixed' && $type->getName() !== 'null'
            ? '?' . $name
            : $name;
    }

    private function slotFor(TemplateDefinition $definition, SlotKind $kind, string $memberName): ?SlotDefinition
    {
        foreach ($definition->slots as $slot) {
            if ($slot->kind === $kind && $slot->memberName === $memberName) {
                return $slot;
            }
        }

        return null;
    }

    private function parameterSlot(TemplateDefinition $definition, string $methodName, int $index): ?SlotDefinition
    {
        foreach ($definition->slots as $slot) {
            if ($slot->kind              === SlotKind::Parameter
                && $slot->memberName     === $methodName
                && $slot->parameterIndex === $index
            ) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * @param ReflectionClass<object>             $reflection
     * @param ReflectionProperty|ReflectionMethod $member
     */
    private function isOwnedBy(ReflectionClass $reflection, ReflectionProperty|ReflectionMethod $member): bool
    {
        return $member->getDeclaringClass()->getName() === $reflection->getName();
    }
}

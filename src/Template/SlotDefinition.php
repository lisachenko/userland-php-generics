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

namespace Lisachenko\Generics\Template;

/**
 * One declaration slot of a template that carries a type parameter
 *
 * Slots are discovered once per template and describe *where* a substitution has to happen;
 * the substitution strategies turn them into whatever the engine needs.
 */
final class SlotDefinition
{
    /**
     * @param SlotKind    $kind              Which kind of declaration this is
     * @param string      $memberName        Property name, or method name for parameters and returns
     * @param int|null    $parameterIndex    Zero-based parameter position, for Parameter slots only
     * @param string|null $parameterName     Parameter variable name, for Parameter slots only
     * @param string      $templateParameter Name of the type parameter this slot carries, e.g. `T`
     * @param SlotForm    $form              How the slot announces its type parameter
     * @param string|null $declaredTypeName  Fully-qualified placeholder type name, Placeholder form only
     * @param bool        $nullable          Whether the substituted type has to accept null
     */
    private function __construct(
        public readonly SlotKind $kind,
        public readonly string $memberName,
        public readonly ?int $parameterIndex,
        public readonly ?string $parameterName,
        public readonly string $templateParameter,
        public readonly SlotForm $form,
        public readonly ?string $declaredTypeName,
        public readonly bool $nullable = false,
    ) {}

    public static function attributeProperty(string $propertyName, string $templateParameter, bool $nullable): self
    {
        return new self(SlotKind::Property, $propertyName, null, null, $templateParameter, SlotForm::Attribute, null, $nullable);
    }

    public static function attributeParameter(
        string $methodName,
        int $parameterIndex,
        string $parameterName,
        string $templateParameter,
        bool $nullable,
    ): self {
        return new self(
            SlotKind::Parameter,
            $methodName,
            $parameterIndex,
            $parameterName,
            $templateParameter,
            SlotForm::Attribute,
            null,
            $nullable,
        );
    }

    public static function attributeReturnType(string $methodName, string $templateParameter, bool $nullable): self
    {
        return new self(SlotKind::ReturnType, $methodName, null, null, $templateParameter, SlotForm::Attribute, null, $nullable);
    }

    public static function placeholderProperty(
        string $propertyName,
        string $templateParameter,
        string $declaredTypeName,
    ): self {
        return new self(
            SlotKind::Property,
            $propertyName,
            null,
            null,
            $templateParameter,
            SlotForm::Placeholder,
            $declaredTypeName,
        );
    }

    public static function placeholderParameter(
        string $methodName,
        int $parameterIndex,
        string $parameterName,
        string $templateParameter,
        string $declaredTypeName,
    ): self {
        return new self(
            SlotKind::Parameter,
            $methodName,
            $parameterIndex,
            $parameterName,
            $templateParameter,
            SlotForm::Placeholder,
            $declaredTypeName,
        );
    }

    public static function placeholderReturnType(
        string $methodName,
        string $templateParameter,
        string $declaredTypeName,
    ): self {
        return new self(
            SlotKind::ReturnType,
            $methodName,
            null,
            null,
            $templateParameter,
            SlotForm::Placeholder,
            $declaredTypeName,
        );
    }

    /**
     * Renders the slot the way a PHP error message would name it
     */
    public function describe(string $className): string
    {
        return match ($this->kind) {
            SlotKind::Property  => sprintf('property %s::$%s', $className, $this->memberName),
            SlotKind::Parameter => sprintf(
                'parameter #%d ($%s) of %s::%s()',
                $this->parameterIndex ?? 0,
                $this->parameterName  ?? '?',
                $className,
                $this->memberName,
            ),
            SlotKind::ReturnType => sprintf('return type of %s::%s()', $className, $this->memberName),
        };
    }
}

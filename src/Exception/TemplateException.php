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

namespace Lisachenko\Generics\Exception;

use LogicException;

/**
 * Raised when a class cannot be used as a generic template
 *
 * Everything here is a declaration problem in the template itself, detected while parsing
 * it and always before the engine is asked to do anything.
 */
final class TemplateException extends LogicException implements GenericsException
{
    public static function notATemplate(string $className): self
    {
        return new self(sprintf(
            'Class %s is not a generic template: it declares no #[TemplateParameter] attribute.',
            $className,
        ));
    }

    public static function missingMarkerInterface(string $className, string $interfaceName): self
    {
        return new self(sprintf(
            'Generic template %s must implement %s. A specialization is a sibling of its template '
            . 'rather than a subclass, so an interface is the only relation that survives '
            . 'monomorphization; see docs/instanceof-and-identity.md.',
            $className,
            $interfaceName,
        ));
    }

    public static function duplicateParameter(string $className, string $parameterName): self
    {
        return new self(sprintf(
            'Generic template %s declares the type parameter %s more than once.',
            $className,
            $parameterName,
        ));
    }

    public static function noParameters(string $className): self
    {
        return new self(sprintf(
            'Generic template %s declares #[TemplateParameter] with no parameters.',
            $className,
        ));
    }

    public static function placeholderInCompositeType(
        string $className,
        string $slotDescription,
        string $parameterName,
    ): self {
        return new self(sprintf(
            'Type parameter %s of generic template %s appears inside the union or intersection type '
            . 'of %s. The engine cannot substitute a type inside a type list, so a placeholder slot '
            . 'must be a single type.',
            $parameterName,
            $className,
            $slotDescription,
        ));
    }

    public static function noSubstitutionStrategy(string $className): self
    {
        return new self(sprintf(
            'No substitution strategy can handle generic template %s. This usually means the '
            . 'template mixes slot forms that the installed z-engine version cannot serve; see '
            . 'docs/design-notes.md.',
            $className,
        ));
    }

    public static function alreadySpecialized(string $className): self
    {
        return new self(sprintf(
            'Class %s is already a specialization and cannot be specialized again. Call of() on the '
            . 'template instead.',
            $className,
        ));
    }
}

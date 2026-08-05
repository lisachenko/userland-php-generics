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

use Lisachenko\Generics\Attribute\TemplateParameter;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;

/**
 * Reads `#[TemplateParameter]` the way an analyser has to read it
 *
 * The runtime has `TemplateParser` for this, but it works on live reflection and throws a
 * diagnostic exception when a template is malformed. An analyser sees classes it cannot load
 * and code that is mid-edit, so it needs the same question answered without instantiating
 * anything and without failing: attribute arguments are read raw rather than through
 * `newInstance()`, so an argument the analyser cannot evaluate degrades to "not a template"
 * instead of throwing inside a rule.
 */
final class TemplateReflection
{
    public static function isTemplate(ClassReflection $class): bool
    {
        return self::typeParameterNames($class) !== [];
    }

    /**
     * The declared type parameter names, in declaration order
     *
     * @return list<string> Empty when the class is not a template
     */
    public static function typeParameterNames(ClassReflection $class): array
    {
        $names = [];
        foreach ($class->getNativeReflection()->getAttributes(TemplateParameter::class) as $attribute) {
            $arguments = $attribute->getArguments();
            $name      = $arguments[0] ?? $arguments['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * The bound declared for each type parameter, keyed by parameter name
     *
     * @return array<string, string>
     */
    public static function bounds(ClassReflection $class): array
    {
        $bounds = [];
        foreach ($class->getNativeReflection()->getAttributes(TemplateParameter::class) as $attribute) {
            $arguments = $attribute->getArguments();
            $name      = $arguments[0] ?? $arguments['name'] ?? null;
            $bound     = $arguments[1] ?? $arguments['of'] ?? null;
            if (is_string($name) && is_string($bound)) {
                $bounds[$name] = $bound;
            }
        }

        return $bounds;
    }

    /**
     * The type parameter an #[Of]/#[OfReturn] attribute names, without instantiating it
     *
     * The analyser hands back its own reflection adapters rather than PHP's
     * `ReflectionAttribute`, and they are not related by any shared type - only by both
     * exposing `getArguments()`. Narrowing that in one place keeps the duck-typing out of the
     * rules, which are the part worth reading.
     *
     * @param array<mixed> $attributes As returned by any reflection's getAttributes()
     */
    public static function markedParameter(array $attributes): ?string
    {
        $first = $attributes[0] ?? null;
        if (!is_object($first) || !method_exists($first, 'getArguments')) {
            return null;
        }

        $arguments = $first->getArguments();
        if (!is_array($arguments)) {
            return null;
        }
        $parameter = $arguments[0] ?? $arguments['parameter'] ?? null;

        return is_string($parameter) ? $parameter : null;
    }

    /**
     * Resolves a class name to its reflection, or null when it cannot be seen
     *
     * A rule must never explode on a name the analyser has no reflection for - an unfinished
     * refactor is a normal thing for it to be looking at.
     */
    public static function find(ReflectionProvider $provider, string $className): ?ClassReflection
    {
        return $provider->hasClass($className) ? $provider->getClass($className) : null;
    }
}

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

namespace Lisachenko\Generics\Naming;

/**
 * Names specializations so that they stay legal PHP identifiers: `App\Generic\Box_int`
 *
 * Opt-in, and it costs something real to opt in. `AngleBracketNameMangler` is the default
 * because no PHP source can declare a class containing `<` and no PSR-4 autoloader can resolve
 * one, which is what makes a collision with a hand-written class *impossible* rather than
 * merely unlikely. This mangler gives that guarantee up. Use it only when a tool in the chain
 * refuses to handle a name it cannot parse - a profiler, a serializer, a log indexer - and
 * accept the two consequences below.
 *
 * **It can collide.** `App\Generic\Box_int` is a name somebody could have written by hand. The
 * inserted `Generic` namespace segment makes that unlikely, not impossible.
 *
 * **It cannot always run backwards.** Arguments are joined with `_` and class-typed arguments
 * have their separators flattened to `_`, so a template or argument whose own name contains an
 * underscore is ambiguous: `Foo_Bar_int` may be `Foo_Bar<int>` or `Foo<Bar_int>`. `parse()` is
 * therefore best-effort. It is not a hole in the identity API, because `GenericFactory` asks
 * `SpecializationRegistry` first and only falls back to parsing for a name this process did not
 * mint - see `NameMangler::parse()`.
 */
final class IdentifierSafeNameMangler implements NameMangler
{
    /**
     * The namespace segment inserted before the short name
     *
     * It is what keeps `Box_int` from sitting directly beside the `Box` it came from, where a
     * collision with a hand-written class would be far more likely.
     */
    private const SEGMENT = 'Generic';

    private const SEPARATOR = '_';

    public function mangle(string $templateName, array $typeArguments): string
    {
        $namespace = $this->namespaceOf($templateName);
        $shortName = $this->shortNameOf($templateName);

        $parts = [$shortName];
        foreach ($typeArguments as $typeArgument) {
            $parts[] = $this->flatten($typeArgument);
        }

        return $namespace . self::SEGMENT . '\\' . implode(self::SEPARATOR, $parts);
    }

    public function isMangled(string $className): bool
    {
        $namespace = $this->namespaceOf($className);
        if (!str_ends_with($namespace, self::SEGMENT . '\\')) {
            return false;
        }

        // A bare `App\Generic\Box` carries no arguments, so it is somebody's own class rather
        // than one of ours; every mangled name has at least one separator.
        return str_contains($this->shortNameOf($className), self::SEPARATOR);
    }

    /**
     * Best-effort - see the class docblock, and prefer `SpecializationRegistry` where it can answer
     */
    public function parse(string $className): ?MangledName
    {
        if (!$this->isMangled($className)) {
            return null;
        }

        $parts = explode(self::SEPARATOR, $this->shortNameOf($className));

        // The first part is the template's short name, the rest are the arguments; a name with
        // no argument left after the split is not one of ours.
        $shortName = array_shift($parts);
        if ($shortName === '' || $parts === []) {
            return null;
        }

        $namespace = substr($this->namespaceOf($className), 0, -strlen(self::SEGMENT . '\\'));
        foreach ($parts as $part) {
            if ($part === '') {
                return null;
            }
        }

        /** @var class-string $templateName */
        $templateName = $namespace . $shortName;

        return MangledName::of($templateName, $parts);
    }

    /**
     * Everything up to and including the last separator, or `''` for the global namespace
     */
    private function namespaceOf(string $className): string
    {
        $position = strrpos($className, '\\');

        return $position === false ? '' : substr($className, 0, $position + 1);
    }

    private function shortNameOf(string $className): string
    {
        $position = strrpos($className, '\\');

        return $position === false ? $className : substr($className, $position + 1);
    }

    /**
     * Turns a type argument into something that can appear inside an identifier
     *
     * A nested argument arrives already mangled by this same mangler, so it is a class name
     * like `App\Generic\Box_int` and flattens the same way as any other.
     */
    private function flatten(string $typeArgument): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_\x80-\xff]/', self::SEPARATOR, $typeArgument);
    }
}

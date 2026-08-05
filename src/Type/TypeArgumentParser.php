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

/**
 * Parses the small type grammar this library accepts
 *
 * Deliberately a subset of PHPStan's type syntax - `int`, `?App\User`, `Box<int>`,
 * `Map<string,Box<int>>` - so the same strings mean the same thing to the runtime and to the
 * shipped PHPStan extension. Anything richer than that (unions, intersections, array shapes)
 * has no representation in a `zend_type` slot and is rejected here rather than misread.
 *
 * A hand-written balanced-bracket scanner rather than a dependency: the grammar is four
 * productions and the error messages need to name the offending argument.
 */
final class TypeArgumentParser
{
    public function parse(string $templateName, string $argument): TypeArgument
    {
        $trimmed = trim($argument);
        if ($trimmed === '') {
            throw TypeArgumentException::malformed($templateName, $argument, 'it is empty');
        }

        $nullable = false;
        if (str_starts_with($trimmed, '?')) {
            $nullable = true;
            $trimmed  = trim(substr($trimmed, 1));
        }

        if (str_contains($trimmed, '|') || str_contains($trimmed, '&')) {
            throw TypeArgumentException::malformed(
                $templateName,
                $argument,
                'union and intersection types cannot be written into a declaration slot',
            );
        }

        $open = strpos($trimmed, '<');
        if ($open === false) {
            if (str_contains($trimmed, '>')) {
                throw TypeArgumentException::malformed($templateName, $argument, 'it has an unbalanced ">"');
            }

            return new TypeArgument($this->normalizeName($templateName, $argument, $trimmed), $nullable);
        }

        if (!str_ends_with($trimmed, '>')) {
            throw TypeArgumentException::malformed($templateName, $argument, 'it has an unbalanced "<"');
        }

        $name  = $this->normalizeName($templateName, $argument, trim(substr($trimmed, 0, $open)));
        $inner = substr($trimmed, $open + 1, -1);

        $nested = [];
        foreach ($this->splitTopLevel($templateName, $argument, $inner) as $part) {
            $nested[] = $this->parse($templateName, $part);
        }

        return new TypeArgument($name, $nullable, $nested);
    }

    private function normalizeName(string $templateName, string $argument, string $name): string
    {
        $normalized = ltrim($name, '\\');
        if ($normalized === '') {
            throw TypeArgumentException::malformed($templateName, $argument, 'it has an empty type name');
        }

        return $normalized;
    }

    /**
     * Splits on commas that are not inside a nested argument list
     *
     * @return list<string>
     */
    private function splitTopLevel(string $templateName, string $argument, string $inner): array
    {
        $parts  = [];
        $depth  = 0;
        $start  = 0;
        $length = strlen($inner);

        for ($index = 0; $index < $length; $index++) {
            $character = $inner[$index];
            if ($character === '<') {
                $depth++;
            } elseif ($character === '>') {
                $depth--;
                if ($depth < 0) {
                    throw TypeArgumentException::malformed($templateName, $argument, 'it has an unbalanced ">"');
                }
            } elseif ($character === ',' && $depth === 0) {
                $parts[] = substr($inner, $start, $index - $start);
                $start   = $index + 1;
            }
        }

        if ($depth !== 0) {
            throw TypeArgumentException::malformed($templateName, $argument, 'it has an unbalanced "<"');
        }
        $parts[] = substr($inner, $start);

        return $parts;
    }
}

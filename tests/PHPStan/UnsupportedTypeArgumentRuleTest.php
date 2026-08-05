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

use Lisachenko\Generics\PHPStan\Rule\UnsupportedTypeArgumentRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<UnsupportedTypeArgumentRule>
 */
final class UnsupportedTypeArgumentRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new UnsupportedTypeArgumentRule($this->createReflectionProvider());
    }

    public function testOnlyArgumentsTheEngineCannotWriteAreReported(): void
    {
        // The `iterable` and `callable` wordings come from BuiltinTypes::rejectionReasonFor(),
        // the same table the runtime exception is built from - if the two ever diverged, this
        // is where it would show up
        $this->analyse([__DIR__ . '/data/type-arguments.php'], [
            [
                'Type argument "iterable" is unsupported: iterable is a union of array and '
                . 'Traversable, which cannot be substituted.',
                14,
            ],
            [
                'Type argument "callable" is unsupported: callable is not expressible as a '
                . 'property type and has no engine type mask.',
                15,
            ],
            [
                'Type argument "?int" is nullable. Substitution preserves the nullability the '
                . 'template declared and cannot introduce it, so declare the slot as "?T" in the '
                . 'template instead.',
                16,
            ],
            [
                'Type argument "int|string" is a union or intersection type, which cannot be '
                . 'written into a declaration slot.',
                17,
            ],
            [
                'Type argument "resource" is unsupported: resource is not a declarable type in PHP.',
                21,
            ],
        ]);
    }
}

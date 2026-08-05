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

/**
 * Materializes a nested type argument such as the inner `Box<int>` of `Box<Box<int>>`
 *
 * Nested generics resolve eagerly and depth-first: the inner specialization has to be a real
 * registered class before the outer one can name it, because the outer slot stores nothing
 * but that name and the engine resolves it lazily at the first type check.
 *
 * Implemented by GenericFactory; expressed as an interface so the resolver depends on the one
 * operation it needs rather than on the whole factory.
 */
interface NestedTypeResolver
{
    /**
     * @param  class-string  $templateName
     * @param  list<string>  $typeArguments
     * @return class-string
     */
    public function specializeNested(string $templateName, array $typeArguments): string;
}

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

namespace Lisachenko\Generics\Attribute;

use Attribute;

/**
 * Marks a property or parameter as carrying the named type parameter
 *
 * The alternative to naming the type parameter in the declaration itself. Use it when the
 * declared type cannot be the placeholder - a `mixed` property is the common case - or when
 * one placeholder class is reused by slots that need different type parameters.
 *
 * ```php
 * #[Of('T')]
 * private mixed $first = null;
 * ```
 *
 * A promoted constructor property carrying this attribute produces **two** slots, the
 * parameter and the property, because native reflection reports the attribute on both.
 *
 * Parameters and return types have a restriction properties do not: their declared type must
 * be class-like. See docs/limitations.md - the engine compiles the check for a builtin-typed
 * parameter into opcodes the specialization shares with its template.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Of
{
    public function __construct(public string $parameter) {}
}

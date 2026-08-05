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
 * Marks a method's return type as carrying the named type parameter
 *
 * Separate from #[Of] because PHP has no attribute target for a return type; the attribute
 * goes on the method and speaks about what it returns.
 *
 * The declared return type must be class-like, for the reason given in #[Of].
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class OfReturn
{
    public function __construct(public string $parameter) {}
}

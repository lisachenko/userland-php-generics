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
 * The three kinds of declaration slot a type parameter can occupy
 *
 * These are exactly the places the engine stores a `zend_type` that it enforces: a property
 * declaration, a parameter declaration and a return-type declaration. Element types inside
 * `array<T>` are not among them, which is why they cannot be enforced at runtime.
 */
enum SlotKind
{
    case Property;
    case Parameter;
    case ReturnType;
}

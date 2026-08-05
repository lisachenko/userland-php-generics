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

namespace Lisachenko\Generics;

/**
 * Marker every generic template must implement
 *
 * A specialization is a *sibling* of its template, not a subclass: `$box instanceof Box` is
 * false for `Box<int>`. Interfaces, on the other hand, are preserved and shared onto every
 * copy, so this marker is the one relation that survives monomorphization and is therefore
 * required rather than optional - see docs/instanceof-and-identity.md.
 */
interface GenericObject {}

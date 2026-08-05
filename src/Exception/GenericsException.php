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

use Throwable;

/**
 * Common contract for every failure this library raises
 *
 * Catching this interface catches everything the generics runtime can throw, without
 * catching the engine-level TypeError that a specialized class produces on its own -
 * that one is the whole point of the library and must reach the caller unchanged.
 */
interface GenericsException extends Throwable {}

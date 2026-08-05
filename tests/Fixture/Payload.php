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

namespace Lisachenko\Generics\Fixture;

/**
 * A plain class used as a class-typed type argument
 */
final class Payload
{
    public function __construct(public readonly string $label = 'payload') {}
}

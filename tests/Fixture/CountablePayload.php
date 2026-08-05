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

use Countable;

/**
 * Satisfies a `Countable` bound
 */
final class CountablePayload implements Countable
{
    public function count(): int
    {
        return 0;
    }
}

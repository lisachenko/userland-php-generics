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

namespace Lisachenko\Generics\Benchmark\Fixture;

/**
 * The call surface a timing loop needs
 *
 * Synthesized classes only carry this when their `m0()` takes `mixed`, which is exactly the
 * shape the dispatch scenario compares - a narrower parameter could not satisfy it, and PHP
 * would refuse the declaration rather than let a timing loop call something unexpected.
 */
interface BenchmarkSubject
{
    public function m0(mixed $value): int;
}

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

namespace Lisachenko\Generics\Benchmark\Scenario;

/**
 * One measurable question about monomorphization
 */
interface Scenario
{
    /**
     * Slug used on the command line and as the JSON key
     */
    public function key(): string;

    /**
     * @param bool $smoke Run the smallest grid that still exercises every code path, for CI
     */
    public function run(bool $smoke): ScenarioReport;
}

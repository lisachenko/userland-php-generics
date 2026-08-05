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
 * One scenario's finished output: what it measured, the rows, and what the rows mean
 *
 * `$findings` is not decoration. A table of numbers with no stated reading is how a benchmark
 * ends up quoted for something it never showed, and the whole point of this harness is to
 * produce a claim that survives being checked.
 */
final class ScenarioReport
{
    /**
     * @param list<string>                     $columns
     * @param list<array<string, string|int|float>> $rows
     * @param list<string>                     $findings
     */
    public function __construct(
        public readonly string $title,
        public readonly string $question,
        public readonly array $columns,
        public readonly array $rows,
        public readonly array $findings = [],
    ) {}
}

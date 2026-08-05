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

namespace Lisachenko\Generics\BenchmarkHarness;

use Lisachenko\Generics\Benchmark\Scenario\CallDispatchScenario;
use Lisachenko\Generics\Benchmark\Scenario\CodegenBaselineScenario;
use Lisachenko\Generics\Benchmark\Scenario\MonomorphizationMemoryScenario;
use Lisachenko\Generics\Benchmark\Scenario\ScaleOutScenario;
use Lisachenko\Generics\Benchmark\Scenario\Scenario;
use Lisachenko\Generics\Benchmark\Scenario\SpecializationLatencyScenario;
use Lisachenko\Generics\RequiresEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Keeps the benchmark harness from rotting
 *
 * The harness is the least-exercised code in the package: nothing depends on it, so a change
 * to the substitution path can break it without breaking anything else, and it would stay
 * broken until somebody tried to publish numbers. These assertions are about the harness
 * running and producing a well-formed report - **never** about a number, which would be a
 * flaky test dressed up as a regression guard.
 */
final class HarnessSmokeTest extends TestCase
{
    use RequiresEngine;

    /**
     * @return iterable<string, array{Scenario}>
     */
    public static function scenarios(): iterable
    {
        yield 'memory' => [new MonomorphizationMemoryScenario()];
        yield 'codegen baseline' => [new CodegenBaselineScenario()];
        yield 'latency' => [new SpecializationLatencyScenario()];
        yield 'scale out' => [new ScaleOutScenario()];
        yield 'dispatch' => [new CallDispatchScenario()];
    }

    #[DataProvider('scenarios')]
    public function testScenarioProducesAWellFormedReport(Scenario $scenario): void
    {
        $report = $scenario->run(true);

        self::assertNotSame('', $report->title);
        self::assertNotSame('', $report->question);
        self::assertNotSame([], $report->columns);
        self::assertNotSame([], $report->rows, 'a scenario that measured nothing has nothing to report');

        // Every declared column has to be present in every row, or the rendered table silently
        // shifts a value into the wrong column
        foreach ($report->rows as $row) {
            foreach ($report->columns as $column) {
                self::assertArrayHasKey($column, $row);
            }
        }
    }

    #[DataProvider('scenarios')]
    public function testScenarioExplainsItsOwnNumbers(Scenario $scenario): void
    {
        // A table with no stated reading is how a benchmark gets quoted for something it never
        // showed; the findings are part of the deliverable, not commentary on it
        self::assertNotSame([], $scenario->run(true)->findings);
    }
}

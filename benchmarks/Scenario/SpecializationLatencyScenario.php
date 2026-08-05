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

use Lisachenko\Generics\Benchmark\Fixture\MethodSlot;
use Lisachenko\Generics\Benchmark\Fixture\TemplateShape;
use Lisachenko\Generics\Benchmark\Fixture\TemplateSynthesizer;
use Lisachenko\Generics\Benchmark\Report\LinearFit;
use Lisachenko\Generics\GenericFactory;

/**
 * What it costs to mint a specialization, and what it costs to ask for one again
 *
 * The second number is the one that decides whether a template can be specialized on a hot
 * path: `of()` is called wherever a generic type is written, so if the memoized call were not
 * a hash lookup the API would be a trap.
 */
final class SpecializationLatencyScenario implements Scenario
{
    public function key(): string
    {
        return 'latency';
    }

    public function run(bool $smoke): ScenarioReport
    {
        $samples = $smoke ? 5 : 200;
        $rows    = [];

        foreach ($this->grid($smoke) as $shape) {
            $rows[] = $this->measure($shape, $samples);
        }

        return new ScenarioReport(
            'Specialization latency',
            'How long does minting a specialization take, and how long does asking for an '
            . 'existing one take?',
            ['slot', 'M', 'K', 'median (us)', 'p95 (us)', 'memoized (us)'],
            $rows,
            $this->findings($rows),
        );
    }

    /**
     * @param  list<array<string, string|int|float>> $rows
     * @return list<string>
     */
    private function findings(array $rows): array
    {
        // Cost against method count, per slot kind: the intercept is what a specialization
        // costs before any method is copied, the slope is what each method adds
        $byMethodCount = [];
        $memoized      = [];
        foreach ($rows as $row) {
            $slot    = $row['slot'];
            $methods = $row['M'];
            $median  = $row['median (us)'];
            $lookup  = $row['memoized (us)'];
            assert(is_string($slot) && is_int($methods) && is_numeric($median) && is_numeric($lookup));

            $byMethodCount[$slot][] = [$methods, (float) $median];
            $memoized[]             = (float) $lookup;
        }

        $findings = [];
        foreach ($byMethodCount as $slot => $points) {
            if (count($points) < 2) {
                continue;
            }
            $fit        = LinearFit::through($points);
            $findings[] = sprintf(
                '`%s`: about **%.0f us fixed** per specialization plus **%.0f us per own '
                . 'method** (r2 %.3f). Every method pays it whether or not it has a substituted '
                . 'slot, because every one has its `zend_op_array` struct copied.',
                $slot,
                max(0.0, $fit->intercept),
                $fit->slope,
                $fit->coefficientOfDetermination,
            );
        }

        $findings[] = 'Those are the numbers behind the "specialize at worker boot, not per '
            . 'request" advice: minting is not a hot-path operation.';

        return [
            ...$findings,
            'The work is a long sequence of individual FFI calls from userland rather than one '
            . 'engine-side copy, which is where most of that time goes. It is a property of '
            . 'driving the engine through FFI, not of monomorphization.',
            sprintf(
                'Asking again costs about **%.1f us** regardless of shape - the factory resolves '
                . 'and mangles the name, then finds it in the cache. That is what makes `of()` '
                . 'safe to write wherever a generic type is needed.',
                array_sum($memoized) / count($memoized),
            ),
            'Timings are sensitive to `zend.assertions`: with assertions on, z-engine verifies '
            . 'every relocated operand of a copied opcode array. The environment block above '
            . 'records which setting produced these numbers.',
        ];
    }

    /**
     * @return list<TemplateShape>
     */
    private function grid(bool $smoke): array
    {
        if ($smoke) {
            return [new TemplateShape(1, 4, MethodSlot::ClassParameter)];
        }

        $shapes = [];
        foreach ([MethodSlot::ClassParameter, MethodSlot::BuiltinParameter] as $slot) {
            foreach ([1, 8, 32] as $methods) {
                $shapes[] = new TemplateShape($methods, 20, $slot);
            }
        }

        return $shapes;
    }

    /**
     * @return array<string, string|int|float>
     */
    private function measure(TemplateShape $shape, int $samples): array
    {
        $synthesizer = new TemplateSynthesizer();
        $template    = $synthesizer->template($shape);
        $arguments   = $synthesizer->typeArguments($samples);
        $factory     = new GenericFactory();

        // Warm-up: template parsing and registry population are a one-off that would otherwise
        // land entirely on the first sample
        $factory->specialize($template, $synthesizer->typeArgument());

        $durations = [];
        foreach ($arguments as $argument) {
            $started = hrtime(true);
            $factory->specialize($template, $argument);
            $durations[] = (hrtime(true) - $started) / 1000.0;
        }
        sort($durations);

        // The same call again: the cache should turn it into a name lookup
        $memoized = [];
        for ($round = 0; $round < 1000; ++$round) {
            $started = hrtime(true);
            $factory->specialize($template, $arguments[0]);
            $memoized[] = (hrtime(true) - $started) / 1000.0;
        }
        sort($memoized);

        return [
            'slot'          => $shape->slot->value,
            'M'             => $shape->methods,
            'K'             => $shape->statements,
            'median (us)'   => round($durations[(int) (count($durations) / 2)], 1),
            'p95 (us)'      => round($durations[min((int) (count($durations) * 0.95), count($durations) - 1)], 1),
            'memoized (us)' => round($memoized[(int) (count($memoized) / 2)], 3),
        ];
    }
}

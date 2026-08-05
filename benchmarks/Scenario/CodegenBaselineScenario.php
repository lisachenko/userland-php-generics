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
use Lisachenko\Generics\Benchmark\Report\EngineFacts;
use Lisachenko\Generics\GenericFactory;

/**
 * The same monomorphization done the way a codegen library would do it
 *
 * Without this the memory scenario is a number with nothing to compare it to. A userland
 * generics library that does not have `ClassSpecializer` has exactly one option - emit the
 * source with the concrete type written in and compile it - so that is the baseline, measured
 * on the identical shapes with the identical method.
 *
 * The interesting axis is body size. Both approaches are linear in the number of
 * specializations; only one of them is also linear in how much code each specialization has.
 */
final class CodegenBaselineScenario implements Scenario
{
    public function key(): string
    {
        return 'codegen-baseline';
    }

    public function run(bool $smoke): ScenarioReport
    {
        $total  = $smoke ? 4 : 50;
        $rows   = [];
        $ratios = [];

        foreach ($this->grid($smoke) as $shape) {
            $specialized = $this->measureSpecialization($shape, $total);
            $compiled    = $this->measureCodegen($shape, $total);
            $ratio       = $compiled / max($specialized, 1);

            $ratios[$shape->statements][] = $ratio;
            $rows[]                       = [
                'M'                  => $shape->methods,
                'K'                  => $shape->statements,
                'opcodes/method'     => EngineFacts::opcodeCount($this->probe($shape), 'm0'),
                'specialize (bytes)' => $specialized,
                'codegen (bytes)'    => $compiled,
                'ratio'              => sprintf('%.1fx', $ratio),
            ];
        }

        return new ScenarioReport(
            'Monomorphization vs code generation',
            'How does a class-entry copy compare to emitting and compiling the specialized source?',
            ['M', 'K', 'opcodes/method', 'specialize (bytes)', 'codegen (bytes)', 'ratio'],
            $rows,
            [
                ...$this->findings($ratios),
                'The comparison is memory only. Code generation also pays a full compile per '
                . 'specialization, which the latency scenario measures separately.',
            ],
        );
    }

    /**
     * @param  array<int, list<float>> $ratios Body size => codegen/specialize ratios
     * @return list<string>
     */
    private function findings(array $ratios): array
    {
        $findings = [];
        foreach ($ratios as $statements => $observed) {
            if ($observed === []) {
                continue;
            }
            $findings[] = sprintf(
                'At K=%d statements per method, code generation used between **%.1fx** and '
                . '**%.1fx** what a specialization used.%s',
                $statements,
                min($observed),
                max($observed),
                min($observed) < 1.0
                    ? ' Below 1x the copy is the more expensive of the two: a class entry has a'
                        . ' fixed cost that a body this small never earns back.'
                    : '',
            );
        }

        if (count($ratios) > 1) {
            $findings[] = 'The direction is what matters: the ratio is a function of body size, '
                . 'because only one of the two approaches copies the body. Everything else was '
                . 'held constant between the two columns.';
        }

        return $findings;
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
        foreach ([1, 8, 32] as $methods) {
            foreach ([4, 200] as $statements) {
                $shapes[] = new TemplateShape($methods, $statements, MethodSlot::ClassParameter);
            }
        }

        return $shapes;
    }

    private function measureSpecialization(TemplateShape $shape, int $total): int
    {
        $synthesizer = new TemplateSynthesizer();
        $template    = $synthesizer->template($shape);
        $arguments   = $synthesizer->typeArguments($total);
        $factory     = new GenericFactory();

        $factory->specialize($template, $synthesizer->typeArgument());

        return $this->medianCost(
            $arguments,
            static function (string $argument) use ($factory, $template): void {
                $factory->specialize($template, $argument);
            },
        );
    }

    private function measureCodegen(TemplateShape $shape, int $total): int
    {
        $synthesizer = new TemplateSynthesizer();
        $arguments   = $synthesizer->typeArguments($total);

        // Same warm-up as the specialization side, so neither gets to hide a one-off
        $synthesizer->concrete($shape, $synthesizer->typeArgument());

        return $this->medianCost(
            $arguments,
            static function (string $argument) use ($synthesizer, $shape): void {
                $synthesizer->concrete($shape, $argument);
            },
        );
    }

    /**
     * Median of the per-iteration memory deltas
     *
     * The same robust statistic the memory scenario uses, and for the same reason: both sides
     * grow a global hash table as they go, and a rehash is a step that a mean would spread
     * across every row.
     *
     * @param list<class-string>       $arguments
     * @param callable(class-string): void $iteration
     */
    private function medianCost(array $arguments, callable $iteration): int
    {
        gc_collect_cycles();
        $previous = memory_get_usage();
        $deltas   = [];
        foreach ($arguments as $argument) {
            $iteration($argument);
            $used     = memory_get_usage();
            $deltas[] = $used - $previous;
            $previous = $used;
        }
        sort($deltas);

        return $deltas[intdiv(count($deltas), 2)];
    }

    /**
     * Compiles one template of this shape purely to read its opcode count back
     *
     * @return class-string
     */
    private function probe(TemplateShape $shape): string
    {
        return (new TemplateSynthesizer())->template($shape);
    }
}

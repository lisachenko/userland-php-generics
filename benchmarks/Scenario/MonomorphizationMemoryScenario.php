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
use Lisachenko\Generics\Benchmark\Report\LinearFit;
use Lisachenko\Generics\GenericFactory;

/**
 * What one specialization costs, and what it is a function of
 *
 * This is the scenario the project exists for. The compile-time-generics discussions stall on
 * monomorphization's memory cost, and the answer everyone assumes is "a copy of the code" -
 * which is what a codegen monomorphizer does and what this one does not. The number that
 * settles it is the **slope of memory against specialization count, at two very different body
 * sizes**: if the slope does not move when the bodies get 50x longer, the bodies are shared.
 */
final class MonomorphizationMemoryScenario implements Scenario
{
    public function key(): string
    {
        return 'memory';
    }

    public function run(bool $smoke): ScenarioReport
    {
        $total = $smoke ? 4 : 100;
        $rows  = [];
        $costs = [];

        foreach ($this->grid($smoke) as $shape) {
            $measurement = $this->measure($shape, $total);

            $cost = $measurement['bytes/specialization'];
            assert(is_int($cost));

            $costs[$shape->slot->value][$shape->methods][$shape->statements] = $cost;
            $rows[]                                                          = $measurement;
        }

        return new ScenarioReport(
            'Memory per specialization',
            'What does one specialization cost, and does that cost depend on how large the '
            . 'methods are?',
            ['slot', 'M', 'K', 'opcodes/method', 'bytes/specialization', 'once called', 'fitted slope', 'struct floor', 'overhead', 'r2'],
            $rows,
            [
                ...$this->findings($costs),
                sprintf(
                    '**`once called` is part of the price, and it is quantized.** A copied method '
                    . 'starts with a null run-time cache and a null static-variable table, and the '
                    . 'engine materializes both on first call - so a freshly minted specialization '
                    . 'is cheaper than one an application is using. The engine takes that memory '
                    . 'from its arena in 64 KiB chunks, which over %d specializations makes the '
                    . 'column resolvable only to about %d bytes: a `0` means "below that", not '
                    . '"free".',
                    $total,
                    intdiv(65536, $total),
                ),
                'The headline column is the **median** of the per-specialization deltas, not a '
                . 'least-squares slope. `EG(class_table)` is a hash and rehashes as it fills, '
                . 'which puts a step into the series that a straight line has to absorb; a '
                . 'median ignores it and a slope does not. The fitted slope and its r2 are kept '
                . 'beside it so the two can be compared - where they disagree, the rehash is why.',
            ],
        );
    }

    /**
     * @return list<TemplateShape>
     */
    private function grid(bool $smoke): array
    {
        if ($smoke) {
            return array_map(
                static fn(MethodSlot $slot): TemplateShape => new TemplateShape(1, 4, $slot),
                MethodSlot::cases(),
            );
        }

        $shapes = [];
        foreach (MethodSlot::cases() as $slot) {
            foreach ([1, 8, 32] as $methods) {
                // The two body sizes are the experiment: everything else is held constant, so a
                // slope that moves between them moved because of the body
                foreach ([4, 200] as $statements) {
                    $shapes[] = new TemplateShape($methods, $statements, $slot);
                }
            }
        }

        return $shapes;
    }

    /**
     * @return array<string, string|int|float>
     */
    private function measure(TemplateShape $shape, int $total): array
    {
        $synthesizer = new TemplateSynthesizer();
        $template    = $synthesizer->template($shape);

        // N distinct specializations need N distinct type arguments, and the argument classes
        // are not what is being measured - so they are all compiled before the baseline is taken
        $arguments = $synthesizer->typeArguments($total);
        $factory   = new GenericFactory();

        // One warm-up specialization: it pays for parsing the template and populating the
        // registry, and leaving that in would put a fixed cost inside the first interval
        $factory->specialize($template, $synthesizer->typeArgument());

        $opcodes = EngineFacts::opcodeCount($template, 'm0');

        gc_collect_cycles();
        $baseline    = memory_get_usage();
        $previous    = $baseline;
        $deltas      = [];
        $points      = [];
        $specialized = [];
        foreach ($arguments as $index => $argument) {
            $specialized[] = $factory->specialize($template, $argument);

            $used     = memory_get_usage();
            $deltas[] = $used - $previous;
            $previous = $used;
            $points[] = [$index + 1, $used - $baseline];
        }

        $fit   = LinearFit::through($points);
        $cost  = $this->median($deltas);
        $floor = $this->structFloor($shape, $opcodes);

        return [
            'slot'                 => $shape->slot->value,
            'M'                    => $shape->methods,
            'K'                    => $shape->statements,
            'opcodes/method'       => $opcodes,
            'bytes/specialization' => $cost,
            'once called'          => $this->costOfCalling($specialized, $shape, $arguments),
            'fitted slope'         => (int) round($fit->slope),
            'struct floor'         => $floor,
            'overhead'             => sprintf('%.2fx', $cost / $floor),
            'r2'                   => round($fit->coefficientOfDetermination, 5),
        ];
    }

    /**
     * What a specialization costs once it is actually used
     *
     * A method copy starts with a null run-time cache and a null static-variable table; the
     * engine materializes both on the first call. Measuring only the freshly minted class
     * would therefore report a number no running application ever sees, so this calls one
     * method on every specialization and reports the additional cost per specialization.
     *
     * @param list<class-string> $specialized
     * @param list<class-string> $arguments   Aligned with $specialized: each specialization
     *                                        only accepts its own type argument, which is the
     *                                        whole point of the exercise
     */
    private function costOfCalling(array $specialized, TemplateShape $shape, array $arguments): int
    {
        gc_collect_cycles();
        $before = memory_get_usage();
        foreach ($specialized as $position => $className) {
            // With MethodSlot::None the parameter stayed `int`; every other shape takes an
            // instance of its own type argument
            $argumentClass = $arguments[$position];
            $argument      = $shape->slot === MethodSlot::None ? 1 : new $argumentClass();
            $instance      = new $className();
            for ($index = 0; $index < $shape->methods; ++$index) {
                $instance->{'m' . $index}($argument);
            }
            unset($instance, $argument);
        }
        gc_collect_cycles();

        return intdiv(memory_get_usage() - $before, count($specialized));
    }

    /**
     * @param list<int> $values
     */
    private function median(array $values): int
    {
        sort($values);

        return $values[intdiv(count($values), 2)];
    }

    /**
     * The blocks the copy model says a specialization must allocate
     *
     * Deliberately a *floor* rather than a model of the total: it counts the fixed-size structs
     * the specializer duplicates and nothing else, so the gap between it and the measurement is
     * the hash tables, the zvals and the `zend_string`s - which is worth seeing rather than
     * fitting away.
     */
    private function structFloor(TemplateShape $shape, int $opcodes): int
    {
        $total = EngineFacts::sizeOf('zend_class_entry')
            + ($shape->methods * EngineFacts::sizeOf('zend_op_array'))
            + ($shape->properties * EngineFacts::sizeOf('zend_property_info'));

        if ($shape->slot !== MethodSlot::None) {
            // One parameter plus the return entry, and a substituted method may not share the
            // block the template still uses
            $total += $shape->methods * 2 * EngineFacts::sizeOf('zend_arg_info');
        }

        if ($shape->slot === MethodSlot::BuiltinParameter) {
            // The cached ZEND_RECV mask lives in the opline, so the whole array is un-shared
            $total += $shape->methods * $opcodes * EngineFacts::sizeOf('zend_op');
        }

        return $total;
    }

    /**
     * @param  array<string, array<int, array<int, int>>> $costs
     * @return list<string>
     */
    private function findings(array $costs): array
    {
        $findings = [];
        foreach ($costs as $slot => $byMethodCount) {
            $ratios = [];
            foreach ($byMethodCount as $byStatementCount) {
                $sizes = array_keys($byStatementCount);
                if (count($sizes) < 2) {
                    continue;
                }
                $small = $byStatementCount[min($sizes)];
                $large = $byStatementCount[max($sizes)];
                if ($small > 0) {
                    $ratios[] = $large / $small;
                }
            }
            if ($ratios === []) {
                continue;
            }

            $worst      = max($ratios);
            $findings[] = sprintf(
                '`%s`: growing the bodies by 50x changed the cost per specialization by at most **%.2fx**.%s',
                $slot,
                $worst,
                $slot === MethodSlot::BuiltinParameter->value
                    ? ' This is the one slot kind that un-shares the opcode array, so it is'
                        . ' expected to track body size - and the number says how much that costs.'
                    : ' Bodies are shared, so body size does not appear in the cost.',
            );
        }

        return $findings;
    }
}

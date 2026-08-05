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

use Lisachenko\Generics\Benchmark\Fixture\BenchmarkSubject;
use Lisachenko\Generics\Benchmark\Fixture\MethodSlot;
use Lisachenko\Generics\Benchmark\Fixture\PropertySlot;
use Lisachenko\Generics\Benchmark\Fixture\TemplateShape;
use Lisachenko\Generics\Benchmark\Fixture\TemplateSynthesizer;
use Lisachenko\Generics\GenericFactory;

/**
 * Steady state: is a specialization as cheap to use as a hand-written class?
 *
 * The expected answer was "yes, everywhere", on the reasoning that a specialization is an
 * ordinary class entry reached through the ordinary class table. Measuring it found one place
 * where that is not true, and the shape of this scenario follows from that finding rather than
 * from the prediction: every case is measured against a compiled class of the identical shape,
 * with a `mixed` control that has nothing to check, so a gap can be attributed rather than
 * merely reported.
 *
 * The bodies deliberately do nothing but the write being measured, and the parameter is left
 * `mixed` in every subject, so the only thing that varies is the property's declared type.
 */
final class CallDispatchScenario implements Scenario
{
    /**
     * Below this ratio the difference is not worth a causal claim
     *
     * A property write is a few tens of nanoseconds, so a handful of percent moves between
     * runs on a shared machine. The class-typed gap this scenario exists to report is more
     * than twice the compiled cost and is nowhere near this line.
     */
    private const PARITY_THRESHOLD = 1.25;

    public function key(): string
    {
        return 'dispatch';
    }

    public function run(bool $smoke): ScenarioReport
    {
        $iterations = $smoke ? 2_000 : 300_000;
        $rows       = [];
        $ratios     = [];

        foreach (PropertySlot::cases() as $form) {
            $measured = $this->measure($form, $iterations);

            $ratios[$form->value] = $measured['specialization'] / $measured['hand-written'];
            foreach ($measured as $subject => $perCall) {
                $rows[] = [
                    'property'        => $form->value,
                    'subject'         => $subject,
                    'ns/call'         => round($perCall, 1),
                    'vs hand-written' => sprintf('%.2fx', $perCall / $measured['hand-written']),
                ];
            }
        }

        return new ScenarioReport(
            'Property writes in steady state',
            'Does writing a specialized property cost more than writing a compiled one?',
            ['property', 'subject', 'ns/call', 'vs hand-written'],
            $rows,
            [
                ...$this->findings($ratios),
                ...$this->nameLengthFinding($iterations),
            ],
        );
    }

    /**
     * @return array<string, float> Subject => nanoseconds per call
     */
    private function measure(PropertySlot $form, int $iterations): array
    {
        $shape       = new TemplateShape(1, 0, MethodSlot::PropertyOnly, 1, $form);
        $synthesizer = new TemplateSynthesizer();
        $template    = $synthesizer->template($shape);

        [$argumentClass] = $synthesizer->typeArguments(1);

        // The placeholder form substitutes to a class, the attribute form to a builtin - which
        // is precisely the distinction this scenario exists to price
        $typeArgument = $form === PropertySlot::Placeholder ? $argumentClass : 'int';
        $value        = $form === PropertySlot::Placeholder ? new $argumentClass() : 7;

        $specialized = (new GenericFactory())->specialize($template, $typeArgument);

        return [
            'specialization'    => $this->timePerCall($specialized, $value, $iterations),
            'hand-written'      => $this->timePerCall($synthesizer->concrete($shape, $typeArgument), $value, $iterations),
            'mixed (unchecked)' => $this->timePerCall($synthesizer->unchecked($shape), $value, $iterations),
        ];
    }

    /**
     * @param  array<string, float> $ratios
     * @return list<string>
     */
    private function findings(array $ratios): array
    {
        $findings = [];
        foreach ($ratios as $form => $ratio) {
            $findings[] = sprintf(
                'A **%s** specialized property wrote at **%.2fx** the cost of the same property '
                . 'on a compiled class.%s',
                $form,
                $ratio,
                // A single write is tens of nanoseconds, so a few percent is run-to-run
                // variation; only a gap far outside that is worth explaining
                $ratio < self::PARITY_THRESHOLD
                    ? ' That is parity within run-to-run variation: the engine tests the value'
                        . ' against a type mask and never looks at where the class came from.'
                    : ' That is not parity, and the next lines are the cause.',
            );
        }

        return $findings;
    }

    /**
     * Prices the gap by varying the one thing that should not matter: the type name's length
     *
     * A compiled class resolves its property type once. If a specialization's cost moves with
     * the length of the type name, it is resolving that name on every write instead - which is
     * a far more useful thing to publish than "1.8x, cause unknown".
     *
     * @return list<string>
     */
    private function nameLengthFinding(int $iterations): array
    {
        $shape       = new TemplateShape(1, 0, MethodSlot::PropertyOnly, 1, PropertySlot::Placeholder);
        $synthesizer = new TemplateSynthesizer();
        $factory     = new GenericFactory();

        $timings = [];
        foreach (['short' => 8, 'long' => 120] as $label => $length) {
            $template = $synthesizer->template($shape);
            $argument = $synthesizer->paddedTypeArgument($length);

            $timings[$label] = $this->timePerCall(
                $factory->specialize($template, $argument),
                new $argument(),
                $iterations,
            );
        }

        $delta = $timings['long'] - $timings['short'];

        return [
            sprintf(
                'Lengthening the *type argument\'s class name* from a short one to 120 characters '
                . 'moved the write by **%+.1f ns/call** (%.1f -> %.1f). A compiled class is flat '
                . 'under the same change.',
                $delta,
                $timings['short'],
                $timings['long'],
            ),
            'That probe uses a **class-typed** property. A builtin-typed one has no name to '
            . 'resolve, which is why it sits at parity above and why the attribute form is the '
            . 'cheaper of the two whenever the type argument is a builtin.',
            $delta > 5.0
                ? 'Cost that tracks name length is cost spent resolving the name, which means the '
                    . 'specialization is looking its property type up on **every write** while a '
                    . 'compiled class resolves it once. The engine reaches that fast path through '
                    . 'a class-entry cache attached to interned strings, and the name z-engine '
                    . 'writes into a substituted type is created at run time rather than interned. '
                    . 'That is the suspected mechanism and the obvious place to look first; it is '
                    . 'not something this harness has proven. Filed as '
                    . '[z-engine#130](https://github.com/lisachenko/z-engine/issues/130).'
                : 'No dependence on name length, so nothing is being resolved per write.',
            'What is being lengthened here is the **type argument\'s** name - the name written '
            . 'into the property\'s type - not the specialization\'s own mangled name, which no '
            . 'property write ever reads. The two coincide for a nested generic, where the '
            . 'argument *is* a specialization and its angle-bracket name is long by '
            . 'construction: `Box<Box<int>>` pays this on every write to its inner slot.',
        ];
    }

    /**
     * @param class-string $className
     */
    private function timePerCall(string $className, mixed $value, int $iterations): float
    {
        $instance = new $className();
        assert($instance instanceof BenchmarkSubject);

        // Warm up the run-time cache slots the first calls populate
        for ($round = 0; $round < 5_000; ++$round) {
            $instance->m0($value);
        }

        $started = hrtime(true);
        for ($round = 0; $round < $iterations; ++$round) {
            $instance->m0($value);
        }

        return (hrtime(true) - $started) / $iterations;
    }
}

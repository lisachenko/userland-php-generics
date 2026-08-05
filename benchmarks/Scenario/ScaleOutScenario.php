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
 * What a thousand specializations do to the process
 *
 * Everything else here measures the marginal cost of one. This one asks the question a person
 * deciding whether to adopt the approach actually has: after an application has specialized
 * everything it uses, is the class table itself now a problem?
 */
final class ScaleOutScenario implements Scenario
{
    public function key(): string
    {
        return 'scale-out';
    }

    public function run(bool $smoke): ScenarioReport
    {
        $total       = $smoke ? 10 : 1000;
        $shape       = new TemplateShape(4, 20, MethodSlot::ClassParameter);
        $synthesizer = new TemplateSynthesizer();

        $template  = $synthesizer->template($shape);
        $arguments = $synthesizer->typeArguments($total);
        $factory   = new GenericFactory();

        $lookupBefore = $this->lookupNanoseconds($template);

        gc_collect_cycles();
        $memoryBefore   = memory_get_usage();
        $residentBefore = EngineFacts::residentBytes();

        $started = hrtime(true);
        foreach ($arguments as $argument) {
            $factory->specialize($template, $argument);
        }
        $elapsed = hrtime(true) - $started;

        $used        = memory_get_usage() - $memoryBefore;
        $resident    = EngineFacts::residentBytes();
        $lookupAfter = $this->lookupNanoseconds($template);

        return new ScenarioReport(
            'Scaling out to many specializations',
            sprintf('What do %d live specializations cost, and do they slow the class table down?', $total),
            ['metric', 'value'],
            [
                ['metric' => 'Specializations', 'value' => $total],
                ['metric' => 'Template shape', 'value' => $shape->label()],
                ['metric' => 'Total memory', 'value' => $this->bytes($used)],
                ['metric' => 'Memory per specialization', 'value' => $this->bytes(intdiv($used, $total))],
                ['metric' => 'RSS delta', 'value' => $residentBefore === null || $resident === null
                    ? 'unavailable'
                    : $this->bytes($resident - $residentBefore)],
                ['metric' => 'Total time', 'value' => sprintf('%.1f ms', $elapsed / 1_000_000)],
                ['metric' => 'Time per specialization', 'value' => sprintf('%.1f us', $elapsed / $total / 1000)],
                ['metric' => 'Class lookup before', 'value' => sprintf('%.1f ns', $lookupBefore)],
                ['metric' => 'Class lookup after', 'value' => sprintf('%.1f ns', $lookupAfter)],
            ],
            [
                'Memory per specialization here is a plain total divided by N, not a fitted '
                . 'slope, so it carries the one-off costs the memory scenario deliberately '
                . 'cancels. Read that scenario for the marginal number and this one for the bill.',
                'The class table is a hash, so the lookup figures are expected to match. They '
                . 'are measured because "the class table gets slow" is the objection this '
                . 'approach would otherwise have to answer with an assurance.',
            ],
        );
    }

    /**
     * @param class-string $className
     */
    private function lookupNanoseconds(string $className): float
    {
        $rounds = 100_000;

        // A resolved name the engine has to find in the table rather than short-circuit
        for ($round = 0; $round < 1000; ++$round) {
            class_exists($className, false);
        }

        $started = hrtime(true);
        for ($round = 0; $round < $rounds; ++$round) {
            class_exists($className, false);
        }

        return (hrtime(true) - $started) / $rounds;
    }

    private function bytes(int $value): string
    {
        if (abs($value) < 1024) {
            return sprintf('%d B', $value);
        }
        if (abs($value) < 1024 * 1024) {
            return sprintf('%.1f KiB', $value / 1024);
        }

        return sprintf('%.1f MiB', $value / 1024 / 1024);
    }
}

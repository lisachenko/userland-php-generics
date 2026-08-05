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

use Lisachenko\Generics\Benchmark\Report\MarkdownReport;
use Lisachenko\Generics\Benchmark\Report\SystemInfo;
use Lisachenko\Generics\Benchmark\Scenario\CallDispatchScenario;
use Lisachenko\Generics\Benchmark\Scenario\CodegenBaselineScenario;
use Lisachenko\Generics\Benchmark\Scenario\MonomorphizationMemoryScenario;
use Lisachenko\Generics\Benchmark\Scenario\ScaleOutScenario;
use Lisachenko\Generics\Benchmark\Scenario\Scenario;
use Lisachenko\Generics\Benchmark\Scenario\SpecializationLatencyScenario;

require __DIR__ . '/bootstrap.php';

// Specializations live for the whole process by design, so the harness holds every class it
// mints until the end. Capping that would cap the grid, not the memory.
ini_set('memory_limit', '-1');

$options = getopt('', ['smoke', 'scenario:', 'output:', 'json:', 'help']);
$isSmoke = array_key_exists('smoke', $options);
$json    = is_string($options['json'] ?? null) ? $options['json'] : __DIR__ . '/results/latest.json';

// A smoke run proves the harness still works; its numbers come from a grid too small to mean
// anything, so it must never be able to overwrite the committed document by accident
$markdown = match (true) {
    is_string($options['output'] ?? null) => $options['output'],
    $isSmoke                              => __DIR__ . '/results/smoke.md',
    default                               => __DIR__ . '/../docs/benchmarks.md',
};

/** @var list<Scenario> $scenarios */
$scenarios = [
    new MonomorphizationMemoryScenario(),
    new CodegenBaselineScenario(),
    new SpecializationLatencyScenario(),
    new ScaleOutScenario(),
    new CallDispatchScenario(),
];

if (array_key_exists('help', $options)) {
    fwrite(STDOUT, sprintf(
        "Usage: php benchmarks/run.php [--smoke] [--scenario=KEY] [--output=FILE] [--json=FILE]\n\n"
        . "Scenarios: %s\n",
        implode(', ', array_map(static fn(Scenario $scenario): string => $scenario->key(), $scenarios)),
    ));

    exit(0);
}

if (is_string($options['scenario'] ?? null)) {
    $wanted    = $options['scenario'];
    $scenarios = array_values(array_filter(
        $scenarios,
        static fn(Scenario $scenario): bool => $scenario->key() === $wanted,
    ));
    if ($scenarios === []) {
        fwrite(STDERR, sprintf("Unknown scenario \"%s\"; run with --help for the list.\n", $wanted));

        exit(1);
    }
}

$reports = [];
$raw     = ['system' => SystemInfo::collect(), 'smoke' => $isSmoke, 'scenarios' => []];

foreach ($scenarios as $scenario) {
    fwrite(STDERR, sprintf('Running %s ... ', $scenario->key()));
    $started   = hrtime(true);
    $report    = $scenario->run($isSmoke);
    $reports[] = $report;

    $raw['scenarios'][$scenario->key()] = [
        'title'    => $report->title,
        'question' => $report->question,
        'rows'     => $report->rows,
        'findings' => $report->findings,
    ];

    fwrite(STDERR, sprintf("done in %.1fs\n", (hrtime(true) - $started) / 1_000_000_000));
}

@mkdir(dirname($json), 0o777, true);
file_put_contents($json, json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

// A partial run would rewrite the committed document with a subset of its tables
if (!is_string($options['scenario'] ?? null)) {
    file_put_contents($markdown, (new MarkdownReport())->render($reports, $isSmoke));
    fwrite(STDERR, sprintf("Wrote %s\n", $markdown));
} else {
    fwrite(STDERR, "Single-scenario run: docs/benchmarks.md left alone.\n");
}

fwrite(STDERR, sprintf("Wrote %s\n", $json));

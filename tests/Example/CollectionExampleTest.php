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

namespace Lisachenko\Generics\Example;

use Lisachenko\Generics\RequiresEngine;
use PHPUnit\Framework\TestCase;

/**
 * Keeps the shipped example from rotting
 *
 * An example nobody runs is a promise nobody checked, and this one is the first thing a reader
 * tries. It runs in a subprocess because it declares its own template classes and registers its
 * own specializations in the class table - doing that in the test process would collide with the
 * suite's own names (AGENTS.md section 6).
 *
 * The assertions are on the shape of the output and on the engine's TypeError actually arriving,
 * never on wording that is free to change.
 */
final class CollectionExampleTest extends TestCase
{
    use RequiresEngine;

    public function testTheExampleRunsAndShowsWhatItClaims(): void
    {
        [$status, $output] = self::runExample();

        self::assertSame(0, $status, sprintf("the example exited with %d:\n%s", $status, $output));

        // A real, registered class named after the specialization
        self::assertStringContainsString('Collection<', $output);

        // The engine rejected the wrong type - the entire point of the package
        self::assertStringContainsString('engine TypeError', $output);
        self::assertStringContainsString('must be of type', $output);

        // ...and the template itself was left alone
        self::assertStringContainsString('template untouched', $output);
        self::assertStringContainsString('sibling, not subclass  : false', $output);

        // The two branches that would mean the example silently stopped demonstrating anything
        self::assertStringNotContainsString('UNEXPECTED', $output);
    }

    /**
     * @return array{int, string}
     */
    private static function runExample(): array
    {
        $command = sprintf(
            '%s -d ffi.enable=1 -d opcache.jit=off %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(dirname(__DIR__, 2) . '/examples/collection.php'),
        );

        $lines  = [];
        $status = 0;
        exec($command, $lines, $status);

        return [$status, implode(PHP_EOL, $lines)];
    }
}

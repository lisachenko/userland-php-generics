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
 * Keeps the native-vector example from rotting
 *
 * It runs in a subprocess because it mints `NativeVector<int>` and `NativeVector<float>`, which
 * NativeVectorTest already owns in the test process - two tests registering the same canonical
 * name collide, and the second one is the one that fails (AGENTS.md section 6).
 *
 * The assertions are on the shape of the output and on the engine's TypeError actually
 * arriving, never on wording that is free to change.
 */
final class NativeVectorExampleTest extends TestCase
{
    use RequiresEngine;

    public function testTheExampleRunsAndShowsWhatItClaims(): void
    {
        [$status, $output] = self::runExample();

        self::assertSame(0, $status, sprintf("the example exited with %d:\n%s", $status, $output));

        // Real, registered classes named after the specializations
        self::assertStringContainsString('NativeVector<int>', $output);
        self::assertStringContainsString('NativeVector<float>', $output);

        // The engine rejected the wrong element type - the entire point of the package
        self::assertStringContainsString('engine TypeError', $output);
        self::assertStringContainsString('must be of type int, float given', $output);
        self::assertStringContainsString('must be of type float, string given', $output);

        // The block round-tripped back to a PHP string, and stayed the bytes it was handed out as
        self::assertStringContainsString('still the same bytes   : true', $output);
        self::assertStringContainsString('vector moved on        : 999', $output);

        // ...and a destroyed vector is done
        self::assertStringContainsString('destroyed count        : 0', $output);
        self::assertStringContainsString('access after destroy', $output);

        // The branches that would mean the example silently stopped demonstrating anything
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
            escapeshellarg(dirname(__DIR__, 2) . '/examples/native-vector.php'),
        );

        $lines  = [];
        $status = 0;
        exec($command, $lines, $status);

        return [$status, implode(PHP_EOL, $lines)];
    }
}

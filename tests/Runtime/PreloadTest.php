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

namespace Lisachenko\Generics\Runtime;

use PHPUnit\Framework\TestCase;

/**
 * Boots `preload.php` for real, in a child process
 *
 * Nothing else in the suite can: preload runs once at server start, before any request, so a
 * mistake in it is invisible to every in-process test and surfaces to the user as "my server
 * will not start". The package offers that file as a supported entry point in three places, so
 * something has to actually run it.
 *
 * It has already earned that: the first run found the shipped script printing ten
 * `Can't preload unlinked class` warnings into the server log at every start, because it
 * compiled `src/PHPStan/` - whose classes implement interfaces that exist only when phpstan,
 * a dev dependency, is installed.
 *
 * The second test is the more interesting one. `preload.php` spends twenty-five lines warning
 * that a specialization built during preload does not reach the following request. That turned
 * out to understate it: the attempt segfaults. Asserting it here turns the warning from prose
 * into something that breaks when it stops being true.
 */
final class PreloadTest extends TestCase
{
    protected function setUp(): void
    {
        // The *only* acceptable reason to skip. A preload that is rejected, or that fails to
        // carry the classes it claims to, has to fail rather than quietly not run - which is
        // why composer test:preload exists and runs in the opcache CI job.
        if (!extension_loaded('Zend OPcache')) {
            self::markTestSkipped('Preloading needs the opcache extension');
        }
    }

    public function testTheShippedPreloadScriptStartsAndCarriesItsClasses(): void
    {
        // Autoloading is deliberately never registered in the probe: class_exists(..., false)
        // can only be true if preload really put these into the process
        $result = $this->runWithPreload(
            dirname(__DIR__, 2) . '/preload.php',
            <<<'PROBE'
                $missing = [];
                foreach (['Lisachenko\Generics\Generic', 'Lisachenko\Generics\GenericFactory', 'ZEngine\Core'] as $class) {
                    if (!class_exists($class, false)) {
                        $missing[] = $class;
                    }
                }
                echo $missing === [] ? 'preloaded' : 'missing: ' . implode(', ', $missing);
                PROBE,
        );

        self::assertSame('', $result['stderr'], 'preload reported an error before the script ran');
        self::assertSame(0, $result['exit']);
        self::assertSame('preloaded', $result['stdout']);
    }

    /**
     * The rule preload.php exists to state: a specialization built during preload never arrives
     *
     * The preload request is a request, so its allocations are released when it ends. Measured on
     * PHP 8.4 the attempt segfaults outright rather than losing the class quietly, which is the
     * better of the two failures - but the assertion is deliberately written against the
     * *outcome* rather than the mechanism, because "it crashes" is a property of one build and
     * "you never get a usable specialization" is the property that has to hold on all of them.
     */
    public function testASpecializationMadeDuringPreloadNeverReachesTheRequest(): void
    {
        $script = $this->writeScratchPreloadScript();

        try {
            $result = $this->runWithPreload($script, <<<'PROBE'
                echo class_exists('Lisachenko\Generics\Fixture\Box<int>', false) ? 'survived' : 'gone';
                PROBE);

            self::assertNotSame(
                'survived',
                $result['stdout'],
                'A specialization built during preload reached the following request. That would be '
                . 'good news, but preload.php and docs/long-running.md both say it cannot happen - '
                . 'one of them now needs correcting.',
            );

            // Whichever way it failed, it must not have looked like success
            self::assertTrue(
                $result['exit'] !== 0 || $result['stdout'] === 'gone',
                sprintf('unexpected clean run with output %s', var_export($result['stdout'], true)),
            );
        } finally {
            @unlink($script);
        }
    }

    /**
     * A preload script that does the thing preload.php warns against
     */
    private function writeScratchPreloadScript(): string
    {
        $path = sys_get_temp_dir() . '/generics-preload-' . getmypid() . '.php';
        $root = dirname(__DIR__, 2);

        file_put_contents($path, sprintf(
            <<<'SCRIPT'
                <?php
                declare(strict_types=1);

                require_once %s;

                \ZEngine\Core::preload();
                \Lisachenko\Generics\Generic::warmUp([[\Lisachenko\Generics\Fixture\Box::class, ['int']]]);
                SCRIPT,
            var_export($root . '/vendor/autoload.php', true),
        ));

        return $path;
    }

    /**
     * Runs a probe in a child process with the given preload script active
     *
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function runWithPreload(string $preloadScript, string $probe): array
    {
        $command = [
            PHP_BINARY,
            '-d', 'ffi.enable=1',
            '-d', 'opcache.enable=1',
            '-d', 'opcache.enable_cli=1',
            // The JIT rewrites the executor internals z-engine hooks into (AGENTS.md section 1)
            '-d', 'opcache.jit=off',
            '-d', 'opcache.jit_buffer_size=0',
            '-d', 'opcache.preload=' . $preloadScript,
            '-d', 'display_errors=stderr',
            '-d', 'error_reporting=-1',
        ];

        // PHP refuses to preload as root unless told which user to drop to, and CI runners are
        // not root while this sandbox is - so the option is added only when it is required
        $user = $this->currentUserName();
        if ($user !== null) {
            $command[] = '-d';
            $command[] = 'opcache.preload_user=' . $user;
        }

        $command[] = '-r';
        $command[] = $probe;

        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process, 'could not start a child PHP process');

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exit' => proc_close($process), 'stdout' => trim($stdout), 'stderr' => trim($stderr)];
    }

    private function currentUserName(): ?string
    {
        if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) {
            return null;
        }
        $entry = function_exists('posix_getpwuid') ? posix_getpwuid(0) : false;

        return $entry === false ? 'root' : $entry['name'];
    }
}

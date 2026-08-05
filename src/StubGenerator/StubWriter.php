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

namespace Lisachenko\Generics\StubGenerator;

use RuntimeException;

/**
 * Puts a run's stubs on disk and reports what changed
 *
 * Reporting the change is what lets CI run the generator and fail on a diff, which is how the
 * generated stubs stay honest: a change to the generator that stops matching what the project
 * relies on shows up as a failing build rather than as a stale file nobody re-runs.
 */
final class StubWriter
{
    public function __construct(private readonly string $directory) {}

    /**
     * @param  list<GeneratedStub> $stubs
     * @return list<string>        The files whose contents changed, relative to the directory
     */
    public function write(array $stubs): array
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0o777, true) && !is_dir($this->directory)) {
            throw new RuntimeException(sprintf('Cannot create the stub directory %s.', $this->directory));
        }

        $changed      = [];
        $placeholders = [];
        foreach ($stubs as $stub) {
            $placeholders = [...$placeholders, ...$stub->placeholderNames];
            if ($this->put($stub->fileName(), $stub->render())) {
                $changed[] = $stub->fileName();
            }
        }

        $placeholders = array_values(array_unique($placeholders));
        sort($placeholders);
        if ($placeholders !== [] && $this->put('placeholders.php', StubFileHeader::forPlaceholders($placeholders))) {
            $changed[] = 'placeholders.php';
        }

        return $changed;
    }

    /**
     * Writes only when the contents differ, so an unchanged run leaves mtimes alone
     */
    private function put(string $fileName, string $contents): bool
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . $fileName;
        if (is_file($path) && file_get_contents($path) === $contents) {
            return false;
        }
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Cannot write the stub file %s.', $path));
        }

        return true;
    }
}

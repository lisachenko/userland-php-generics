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

use ZEngine\Core;

/**
 * Point `opcache.preload` at this file, or include it from your own preload script:
 *
 *     opcache.preload      = /path/to/vendor/lisachenko/userland-php-generics/preload.php
 *     opcache.preload_user = www-data
 *
 * ---------------------------------------------------------------------------------------------
 * READ THIS BEFORE ADDING ANYTHING BELOW
 *
 * **Specializing during preload is not supported, and this file must never do it.**
 *
 * The preload request is a request. Its allocations are released when it ends, so a class entry
 * built here cannot survive into the requests that follow. Measured on PHP 8.4, it does not
 * merely vanish: `Generic::warmUp()` inside a preload script **segfaults the process**, so the
 * server does not start at all. That is the kinder of the two possible outcomes - loud rather
 * than silent - but either way there is nothing to gain by trying. PreloadTest asserts that a
 * specialization made here never reaches the following request, however it fails.
 *
 * What preloading *is* good for is the code: z-engine's FFI definitions, this package's classes
 * and - the useful part for an application - your own template classes. Preloaded classes are
 * compiled once at server start and shared by every worker, so the specialization that happens
 * at request or worker-boot time starts from a class entry that is already in memory.
 *
 * See docs/long-running.md for the whole picture and the per-request budget.
 * ---------------------------------------------------------------------------------------------
 */
require_once __DIR__ . '/vendor/autoload.php';

/*
 * Loads z-engine's engine definitions once at server start instead of per request. This is the
 * expensive part of booting the engine, and it is exactly the part preload can carry.
 */
Core::preload();

/*
 * This package's **runtime** classes. `opcache_compile_file()` rather than `class_exists()`:
 * nothing here needs to be *linked* during preload, only compiled and shared.
 *
 * The tooling directories are deliberately excluded. `src/PHPStan/` implements PHPStan's own
 * interfaces, which exist only when phpstan is installed - and it is a dev dependency living
 * inside a phar, so on a production install those classes cannot link at all. Preloading them
 * printed ten `Can't preload unlinked class` warnings into the server log at every start, which
 * is what PreloadTest caught. `src/StubGenerator/` links fine but is reached only from the
 * `generics-stubs` CLI, which never sees this preload.
 *
 * The rule underneath: preload the runtime, not the tooling.
 */
$toolingDirectories = ['PHPStan', 'StubGenerator'];

foreach (new RegexIterator(
    new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/src')),
    '/\.php$/',
) as $file) {
    /** @var SplFileInfo $file */
    $relative = substr($file->getPathname(), strlen(__DIR__ . '/src/'));
    if (in_array(strtok($relative, DIRECTORY_SEPARATOR), $toolingDirectories, true)) {
        continue;
    }

    opcache_compile_file($file->getPathname());
}

/*
 * Your templates go here. Preloading a template is worth doing and entirely safe:
 *
 *     opcache_compile_file(__DIR__ . '/../../../src/Collection.php');
 *
 * Specializing one is not:
 *
 *     Generic::warmUp([[Collection::class, ['int']]]);   // DO NOT - see the note above
 *
 * The warm-up belongs at worker boot, where the class entry it creates lives as long as the
 * process that will use it.
 */

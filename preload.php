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
 * built here does not survive into the requests that follow - and nothing reports that, because
 * nothing failed. `Box::of('int')` would appear to succeed here and the class would simply not
 * be there afterwards.
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
 * This package's own classes. `opcache_compile_file()` rather than `class_exists()`: nothing here
 * needs to be *linked* during preload, only compiled and shared.
 */
foreach (new RegexIterator(
    new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/src')),
    '/\.php$/',
) as $file) {
    /** @var SplFileInfo $file */
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

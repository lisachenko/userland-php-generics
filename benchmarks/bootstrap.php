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

include __DIR__ . '/../vendor/autoload.php';

/*
 * Requiring the autoloader already booted the engine, or left it unbooted because this host
 * cannot run it. The harness has nothing to measure in the second case, so it asks
 * Core::init() - a no-op after a successful boot - for the one-sentence explanation (missing
 * ext-ffi, ffi.enable value, PHP minor mismatch, unsupported platform) and relays that instead
 * of failing later with a stack trace.
 */
try {
    Core::init();
} catch (RuntimeException $bootFailure) {
    fwrite(STDERR, "The benchmark harness drives the engine and cannot boot it here:\n{$bootFailure->getMessage()}\n");

    exit(1);
}

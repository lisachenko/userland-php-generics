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
 * The harness cannot run without the engine, so there is nothing to probe and no reason to
 * re-derive z-engine's environment rules: boot, and if the host cannot support it, relay the
 * one-sentence explanation Core::init() already gives (missing ext-ffi, ffi.enable value,
 * PHP minor mismatch, unsupported platform) instead of a stack trace.
 */
try {
    Core::init();
} catch (RuntimeException $bootFailure) {
    fwrite(STDERR, "The benchmark harness drives the engine and cannot boot it here:\n{$bootFailure->getMessage()}\n");

    exit(1);
}

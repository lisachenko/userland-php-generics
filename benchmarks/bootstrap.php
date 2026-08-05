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

if (!extension_loaded('ffi')) {
    fwrite(STDERR, "The benchmark harness drives the engine and needs ext-ffi.\n");

    exit(1);
}

include __DIR__ . '/../vendor/autoload.php';

Core::init();

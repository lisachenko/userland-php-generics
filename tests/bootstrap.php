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

use Lisachenko\Generics\Runtime\Bootstrap;

ini_set('display_errors', 'on');

include __DIR__ . '/../vendor/autoload.php';

// The engine boot is deliberately best-effort here: the analysis suite must run on a
// host without ext-ffi, and EngineTestCase skips the engine suite with the real reason.
Bootstrap::isAvailable();

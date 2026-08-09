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

ini_set('display_errors', 'on');

/*
 * Requiring the autoloader is the whole boot: z-engine initializes itself from its own
 * Composer bootstrap, and does so silently on a host that cannot run the engine. That is what
 * lets the analysis suite - which tests the PHPStan extension and never touches the engine -
 * run on a host without ext-ffi, while test classes that do drive the engine use the
 * RequiresEngine trait and skip when the boot did not happen.
 *
 * Nothing here re-derives z-engine's environment rules. The previous
 * filter_var(ini_get('ffi.enable')) check got them wrong in both directions: it rejected the
 * supported `preload` mode, and it booted on hosts z-engine refuses.
 */
include __DIR__ . '/../vendor/autoload.php';

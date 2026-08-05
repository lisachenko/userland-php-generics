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

ini_set('display_errors', 'on');

include __DIR__ . '/../vendor/autoload.php';

/*
 * The analysis suite tests the PHPStan extension, which never touches the engine, so it has to
 * run on a host without ext-ffi. Booting unconditionally would make it impossible to run there.
 *
 * This is not defeating Core::init()'s version guard (AGENTS.md section 1) - when FFI *is*
 * available the guard runs exactly as before. Test classes that drive the engine use the
 * RequiresEngine trait, which skips them when this boot did not happen.
 */
if (extension_loaded('ffi') && filter_var(ini_get('ffi.enable'), FILTER_VALIDATE_BOOL)) {
    Core::init();
}

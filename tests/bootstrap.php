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
 * Whether the engine paths can run here is z-engine's question, and Core::isUsable() is its
 * exact answer - a hand-rolled ini_get('ffi.enable') check gets it wrong, because the
 * supported `preload` mode is not a boolean. This is not defeating Core::init()'s version
 * guard (AGENTS.md section 1) - when the environment *is* usable the guard runs exactly as
 * before. Test classes that drive the engine use the RequiresEngine trait, which skips them
 * when this boot did not happen.
 */
if (Core::isUsable()) {
    Core::init();
}

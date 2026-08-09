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

namespace Lisachenko\Generics;

use PHPUnit\Framework\Attributes\Before;
use RuntimeException;
use ZEngine\Core;

/**
 * Skips a test class that needs a booted engine when there is not one, and says why
 *
 * Deliberately not `#[RequiresPhpExtension('ffi')]`, which asks the wrong question: ext-ffi is
 * routinely *loaded* on hosts where `ffi.enable` is `0` or `preload`, and a test that needs to
 * call into the engine cannot run on either. Nor is it a check on the ini value, which is
 * spelled several different ways and means different things per SAPI.
 *
 * It asks z-engine instead, by doing what any code that needs the engine does: calling the
 * idempotent `Core::init()`. Normally that is free - z-engine boots itself from its Composer
 * bootstrap - and it matters in the two cases where it is not. That boot is deliberately silent
 * on a host it cannot run on, so this turns the silence into the sentence explaining it, which
 * a bare `isInitialized()` check cannot do: a skip is what CI's destructive-group gate fails on,
 * and "4 destructive test(s) skipped" with no reason is not something anybody can act on. And
 * where the automatic boot did not happen at all - an old z-engine without the bootstrap, or
 * `ZENGINE_AUTOBOOT=0` - this simply boots the engine and the tests run.
 */
trait RequiresEngine
{
    #[Before]
    protected function skipWithoutABootedEngine(): void
    {
        try {
            Core::init();
        } catch (RuntimeException $reason) {
            self::markTestSkipped(
                'This test drives the Zend Engine, which cannot boot here: ' . $reason->getMessage()
                . ' Run it with ffi.enable=1, for example: '
                . 'php -d ffi.enable=1 -d opcache.jit=off vendor/bin/phpunit',
            );
        }
    }
}

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
use ZEngine\Core;

/**
 * Skips a test class that needs a booted engine when there is not one
 *
 * Deliberately not `#[RequiresPhpExtension('ffi')]`, which asks the wrong question: ext-ffi is
 * routinely *loaded* on hosts where `ffi.enable` is `0` or `preload`, and a test that needs to
 * call into the engine cannot run on either. Nor is it a check on the ini value, which is
 * spelled several different ways and means different things per SAPI.
 *
 * The precondition that actually matters is "did `Core::init()` complete", which the bootstrap
 * attempts exactly once, `Core::isInitialized()` reports exactly, and which is true only when
 * the engine is genuinely usable.
 */
trait RequiresEngine
{
    #[Before]
    protected function skipWithoutABootedEngine(): void
    {
        if (!Core::isInitialized()) {
            self::markTestSkipped(
                'This test drives the Zend Engine. Run it with ffi.enable=1, for example: '
                . 'php -d ffi.enable=1 -d opcache.jit=off vendor/bin/phpunit',
            );
        }
    }
}

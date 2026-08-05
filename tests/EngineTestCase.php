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

use Lisachenko\Generics\Runtime\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that drive the Zend Engine
 *
 * Skips with the real reason rather than failing when the host cannot support FFI, so the
 * suite stays runnable while still being loud about *why* it did not run.
 */
abstract class EngineTestCase extends TestCase
{
    protected function setUp(): void
    {
        if (!Bootstrap::isAvailable()) {
            self::markTestSkipped(Bootstrap::unavailabilityReason() ?? 'The Z-Engine core is unavailable');
        }
    }
}

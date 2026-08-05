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

namespace Lisachenko\Generics\Runtime;

use Lisachenko\Generics\Exception\EngineException;
use Throwable;
use ZEngine\Core;

/**
 * Guards and performs the one-time Z-Engine boot the runtime depends on
 *
 * Booting is idempotent and remembers its outcome: a failed boot is never retried, and
 * the original reason is re-thrown on every subsequent call so the first, most specific
 * diagnostic is the one the caller sees.
 */
final class Bootstrap
{
    private static bool $booted = false;

    private static ?EngineException $failure = null;

    /**
     * Boots the engine, or re-throws the reason it could not be booted
     */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        if (self::$failure !== null) {
            throw self::$failure;
        }

        $failure = self::detectEnvironmentFailure();
        if ($failure !== null) {
            self::$failure = $failure;

            throw $failure;
        }

        try {
            Core::init();
        } catch (Throwable $exception) {
            self::$failure = EngineException::engineBootFailed($exception);

            throw self::$failure;
        }

        self::$booted = true;
    }

    /**
     * Reports whether the engine is usable in this process, without throwing
     *
     * Intended for test skipping and for optional-feature detection; production code
     * should just call boot() and let the exception explain the problem.
     */
    public static function isAvailable(): bool
    {
        try {
            self::boot();
        } catch (EngineException) {
            return false;
        }

        return true;
    }

    /**
     * Explains why the engine is unusable, or null when it booted fine
     */
    public static function unavailabilityReason(): ?string
    {
        return self::isAvailable() ? null : self::$failure?->getMessage();
    }

    private static function detectEnvironmentFailure(): ?EngineException
    {
        if (!extension_loaded('ffi')) {
            return EngineException::ffiExtensionMissing();
        }

        // Core::init() reaches for FFI::scope() first and falls back to FFI::cdef(); under
        // ffi.enable=preload only a preloaded script may do either, so an already-booted
        // engine (preload.php) is the one case where a non-"1" value is still fine.
        $ffiEnable = (string) ini_get('ffi.enable');
        if (!in_array(strtolower($ffiEnable), ['1', 'on', 'true', 'yes'], true) && !isset(Core::$executor)) {
            return EngineException::ffiDisabled($ffiEnable);
        }

        return null;
    }
}

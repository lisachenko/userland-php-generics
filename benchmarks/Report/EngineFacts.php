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

namespace Lisachenko\Generics\Benchmark\Report;

use ZEngine\Core;
use ZEngine\Reflection\ReflectionMethod;

/**
 * The struct sizes the cost model is written in, read from the running engine
 *
 * Publishing a measured slope without these is not reproducible: the same number means
 * something different on a build whose `zend_op_array` is a different size. Everything here
 * comes from the live process rather than a table in a document.
 */
final class EngineFacts
{
    /**
     * @var array<string, int>|null
     */
    private static ?array $sizes = null;

    /**
     * @return array<string, int> C type name => bytes
     */
    public static function structSizes(): array
    {
        if (self::$sizes !== null) {
            return self::$sizes;
        }

        $sizes = [];
        foreach (['zend_class_entry', 'zend_op_array', 'zend_property_info', 'zend_arg_info', 'zend_op'] as $type) {
            $sizes[$type] = Core::sizeOfType($type);
        }

        return self::$sizes = $sizes;
    }

    public static function sizeOf(string $cType): int
    {
        return self::structSizes()[$cType];
    }

    /**
     * How many oplines a compiled method holds
     *
     * This is what makes "K statements" an honest axis: the harness asks for statements and
     * reports the opcodes it actually got.
     *
     * @param class-string $className
     */
    public static function opcodeCount(string $className, string $methodName): int
    {
        $count = 0;
        foreach ((new ReflectionMethod($className, $methodName))->getOpCodes() as $ignored) {
            ++$count;
        }

        return $count;
    }

    /**
     * Resident set size, as a cross-check on the allocator's own accounting
     *
     * `memory_get_usage(false)` counts what `emalloc` handed out, which is exactly the right
     * measure here because z-engine allocates specializations through it. RSS is reported
     * beside it so a reader can see the two agree, on the systems where /proc exists.
     */
    public static function residentBytes(): ?int
    {
        $status = @file_get_contents('/proc/self/status');
        if ($status === false || preg_match('/^VmRSS:\s+(\d+)\s+kB/m', $status, $matches) !== 1) {
            return null;
        }

        return ((int) $matches[1]) * 1024;
    }
}

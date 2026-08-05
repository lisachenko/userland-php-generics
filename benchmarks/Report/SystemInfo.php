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

/**
 * Everything a reader needs to decide whether these numbers apply to their machine
 *
 * Struct layouts are build-specific and the allocator's behaviour is configuration-specific,
 * so a benchmark table without this block is a claim rather than a measurement.
 */
final class SystemInfo
{
    /**
     * @return array<string, string>
     */
    public static function collect(): array
    {
        return [
            'PHP'           => PHP_VERSION,
            'Thread safety' => PHP_ZTS   === 1 ? 'ZTS' : 'NTS',
            'Debug build'   => PHP_DEBUG === 1 ? 'yes' : 'no',
            'Architecture'  => sprintf('%s (%d-bit)', php_uname('m'), PHP_INT_SIZE * 8),
            'OPcache'       => self::opcacheState(),
            'JIT'           => (string) (ini_get('opcache.jit') ?: 'off'),
            // Assertions cost time, not memory: the copy verification in z-engine only runs
            // with them on, so a latency number is only comparable at the same setting
            'zend.assertions' => (string) ini_get('zend.assertions'),
            'CPU'             => self::cpuModel(),
        ];
    }

    private static function opcacheState(): string
    {
        if (!function_exists('opcache_get_status')) {
            return 'not loaded';
        }
        $enabled = (bool) ini_get('opcache.enable_cli');

        return $enabled ? 'enabled (CLI)' : 'loaded, disabled for CLI';
    }

    private static function cpuModel(): string
    {
        $info = @file_get_contents('/proc/cpuinfo');
        if ($info === false || preg_match('/^model name\s*:\s*(.+)$/m', $info, $matches) !== 1) {
            return php_uname('m');
        }

        return trim($matches[1]);
    }
}

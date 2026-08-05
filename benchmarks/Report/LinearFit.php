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

use InvalidArgumentException;

/**
 * Least-squares fit of memory against specialization count
 *
 * The reported cost of a specialization is the **slope**, not a total divided by N. The
 * difference matters: a total carries the one-off cost of the template, of the argument
 * classes and of the allocator's own growth, and dividing by N smears that across every row.
 * The slope cancels all of it, and the intercept makes what was cancelled visible instead of
 * hiding it.
 */
final class LinearFit
{
    private function __construct(
        public readonly float $slope,
        public readonly float $intercept,
        /**
         * How well the straight line describes the samples
         *
         * Printed alongside the slope on purpose: an r2 that is not essentially 1 means the
         * cost is not linear in N and the slope should not be quoted as "bytes per
         * specialization".
         */
        public readonly float $coefficientOfDetermination,
    ) {}

    /**
     * @param list<array{int|float, int|float}> $points
     */
    public static function through(array $points): self
    {
        if (count($points) < 2) {
            throw new InvalidArgumentException('A least-squares fit needs at least two points.');
        }

        $count = count($points);
        $sumX  = $sumY = $sumXY = $sumXX = 0.0;
        foreach ($points as [$x, $y]) {
            $sumX  += $x;
            $sumY  += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }

        $denominator = ($count * $sumXX) - ($sumX * $sumX);
        if ($denominator === 0.0) {
            throw new InvalidArgumentException('A least-squares fit needs at least two distinct x values.');
        }

        $slope     = (($count * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - ($slope * $sumX))            / $count;

        return new self($slope, $intercept, self::determination($points, $slope, $intercept));
    }

    /**
     * @param list<array{int|float, int|float}> $points
     */
    private static function determination(array $points, float $slope, float $intercept): float
    {
        $mean = 0.0;
        foreach ($points as [, $y]) {
            $mean += $y;
        }
        $mean /= count($points);

        $residual = $total = 0.0;
        foreach ($points as [$x, $y]) {
            $residual += (($y - (($slope * $x) + $intercept)) ** 2);
            $total    += (($y - $mean) ** 2);
        }

        // A perfectly flat series has nothing to explain, and the line explains it exactly
        return $total === 0.0 ? 1.0 : 1.0 - ($residual / $total);
    }
}

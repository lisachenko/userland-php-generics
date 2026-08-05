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

/**
 * Remembers which specializations already exist in this process
 *
 * Specializations are registered in the engine's class table for the rest of the request and
 * `ClassSpecializer::specialize()` refuses a duplicate name, so this cache **adopts** rather
 * than fails: a name that is already registered - by a previous factory instance, by a
 * warm-up at worker boot, or by a cache that has been reset - is recorded and returned as-is
 * instead of being specialized a second time.
 */
final class SpecializationCache
{
    /**
     * @var array<string, class-string>
     */
    private array $known = [];

    /**
     * Returns the specialized class name if it is already available, or null
     *
     * @return class-string|null
     */
    public function lookup(string $specializedName): ?string
    {
        if (isset($this->known[$specializedName])) {
            return $this->known[$specializedName];
        }

        // Not ours, but possibly already in the class table: adopt it rather than colliding.
        if (class_exists($specializedName, false)) {
            return $this->remember($specializedName);
        }

        return null;
    }

    /**
     * @return class-string
     */
    public function remember(string $specializedName): string
    {
        /** @var class-string $specializedName */
        return $this->known[$specializedName] = $specializedName;
    }

    /**
     * Forgets the memoized names without touching the engine
     *
     * The classes themselves stay registered - they are engine state, not ours to destroy -
     * so a later lookup adopts them again. This exists for tests and for callers that swap
     * out the factory configuration.
     */
    public function forget(): void
    {
        $this->known = [];
    }

    public function count(): int
    {
        return count($this->known);
    }
}

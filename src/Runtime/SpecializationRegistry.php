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
 * What each specialization was made from
 *
 * Sits beside `SpecializationCache` rather than replacing it, because the two answer different
 * questions. The cache answers *"is this name already available?"* - including for names it did
 * not mint, which is what lets it adopt a class another factory registered. This registry
 * answers *"what was this made from?"*, which can only be said of a name this process actually
 * built, and says it without re-reading the name.
 *
 * That matters most where the name cannot be read back exactly: `IdentifierSafeNameMangler`
 * trades round-tripping for identifier safety, and the registry is what makes `bindingOf()`
 * still exact under it.
 */
final class SpecializationRegistry
{
    /**
     * @var array<string, TypeBinding> Keyed by specialized class name
     */
    private array $bindings = [];

    public function record(TypeBinding $binding): TypeBinding
    {
        return $this->bindings[$binding->specializedName] = $binding;
    }

    public function bindingFor(string $specializedName): ?TypeBinding
    {
        return $this->bindings[$specializedName] ?? null;
    }

    /**
     * Every recorded binding, in the order the specializations were made
     *
     * @return list<TypeBinding>
     */
    public function all(): array
    {
        return array_values($this->bindings);
    }

    /**
     * Forgets the records without touching the engine
     *
     * The classes themselves stay registered - they are engine state, not ours to destroy -
     * exactly as with `SpecializationCache::forget()`. A later `specialize()` for the same
     * arguments adopts the class and records the binding again.
     */
    public function forget(): void
    {
        $this->bindings = [];
    }

    public function count(): int
    {
        return count($this->bindings);
    }
}

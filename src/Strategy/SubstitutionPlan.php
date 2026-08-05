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

namespace Lisachenko\Generics\Strategy;

use ZEngine\Reflection\SlotSubstitutionMap;
use ZEngine\Reflection\TypeSlot;
use ZEngine\Reflection\TypeSubstitutionMap;

/**
 * Accumulates what every strategy wants substituted, then hands over one request
 *
 * Strategies contribute rather than compete: a template is free to declare some slots with a
 * placeholder type and mark others with an attribute, and both end up in the same
 * specialize() call.
 */
final class SubstitutionPlan
{
    /**
     * @var array<string, string>
     */
    private array $byTypeName = [];

    /**
     * @var list<array{TypeSlot, string}>
     */
    private array $bySlot = [];

    public function substituteTypeName(string $placeholderTypeName, string $replacement): void
    {
        $this->byTypeName[$placeholderTypeName] = $replacement;
    }

    public function substituteSlot(TypeSlot $slot, string $replacement): void
    {
        $this->bySlot[] = [$slot, $replacement];
    }

    public function hasSlotSubstitutions(): bool
    {
        return $this->bySlot !== [];
    }

    public function toRequest(): SubstitutionRequest
    {
        return new SubstitutionRequest(
            $this->byTypeName === [] ? null : new TypeSubstitutionMap($this->byTypeName),
            $this->bySlot     === [] ? null : new SlotSubstitutionMap($this->bySlot),
        );
    }
}

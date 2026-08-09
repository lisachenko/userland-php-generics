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

use Lisachenko\Generics\Exception\SpecializationException;
use Lisachenko\Generics\Strategy\SlotAddress;
use Lisachenko\Generics\Strategy\SubstitutionRequest;
use Lisachenko\Generics\Template\SlotKind;
use ZEngine\Core;
use ZEngine\Reflection\ClassSpecializationException;
use ZEngine\Reflection\ClassSpecializer;
use ZEngine\Reflection\SlotSubstitutionMap;
use ZEngine\Reflection\TypeSlot;
use ZEngine\Reflection\TypeSubstitutionMap;

/**
 * The one place in this package that talks to the engine
 *
 * Everything else describes what should happen in this package's own vocabulary; this
 * translates the description into z-engine's, asks it to act, and translates the answer.
 * Confining the dependency to a single class is what makes it possible to reason about when
 * engine state is touched - which is exactly once per specialization, after every validation
 * has already passed.
 */
final class Monomorphizer
{
    public function __construct(private readonly ClassSpecializer $specializer = new ClassSpecializer()) {}

    /**
     * Makes sure the engine is available, or explains why it is not
     *
     * Normally a no-op: z-engine boots itself from its Composer bootstrap, so by the time any
     * of this package runs the bridge is already up. It stays worth calling because that boot
     * is deliberately silent on a host that cannot support it - `Core::init()` is idempotent
     * and re-invocable, so this is either free or the RuntimeException naming the reason
     * (no ext-ffi, ffi.enable=0, a PHP minor z-engine has no verified layouts for).
     */
    public function boot(): void
    {
        Core::init();
    }

    /**
     * Materializes the specialization and registers it in the engine's class table
     *
     * @param  class-string $templateName
     * @return class-string
     */
    public function materialize(
        string $templateName,
        string $specializedName,
        SubstitutionRequest $request,
    ): string {
        $this->boot();

        try {
            // Empty maps stay null: on a z-engine line predating slot substitution the map
            // class does not exist, and a template that never needed it must still work
            $this->specializer->specialize(
                $templateName,
                $specializedName,
                $request->typeSubstitutions === [] ? null : new TypeSubstitutionMap($request->typeSubstitutions),
                $request->needsSlotSubstitution() ? $this->slotSubstitutionsFor($request) : null,
            );
        } catch (ClassSpecializationException $exception) {
            throw SpecializationException::engineRejectedTemplate($templateName, $specializedName, $exception);
        }

        if (!class_exists($specializedName, false)) {
            throw SpecializationException::specializedClassVanished($specializedName);
        }

        /** @var class-string $specializedName */
        return $specializedName;
    }

    /**
     * Translates the request's slot addresses into the engine's slot map
     */
    private function slotSubstitutionsFor(SubstitutionRequest $request): SlotSubstitutionMap
    {
        $substitutions = [];
        foreach ($request->slotSubstitutions as [$slot, $replacement]) {
            $substitutions[] = [$this->typeSlotFor($slot), $replacement];
        }

        return new SlotSubstitutionMap($substitutions);
    }

    private function typeSlotFor(SlotAddress $slot): TypeSlot
    {
        return match ($slot->kind) {
            SlotKind::Property   => TypeSlot::property($slot->memberName),
            SlotKind::Parameter  => TypeSlot::parameter($slot->memberName, $slot->parameterIndex ?? 0),
            SlotKind::ReturnType => TypeSlot::returnType($slot->memberName),
        };
    }
}

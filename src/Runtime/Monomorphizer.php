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
use Lisachenko\Generics\Strategy\SubstitutionRequest;
use ZEngine\Core;
use ZEngine\Reflection\ClassSpecializationException;
use ZEngine\Reflection\ClassSpecializer;

/**
 * The one place in this package that talks to the engine
 *
 * Everything else describes what should happen; this asks z-engine to do it and translates
 * the answer. Confining the dependency to a single class is what makes it possible to reason
 * about when engine state is touched - which is exactly once per specialization, after every
 * validation has already passed.
 */
final class Monomorphizer
{
    public function __construct(private readonly ClassSpecializer $specializer = new ClassSpecializer()) {}

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
        if (!isset(Core::$executor)) {
            Core::init();
        }

        try {
            $this->specializer->specialize(
                $templateName,
                $specializedName,
                $request->typeSubstitutions,
                $request->slotSubstitutions,
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
}

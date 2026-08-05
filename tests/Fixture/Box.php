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

namespace Lisachenko\Generics\Fixture;

use Lisachenko\Generics\Attribute\TemplateParameter;
use Lisachenko\Generics\GenericObject;
use Lisachenko\Generics\GenericTemplate;

/**
 * The canonical single-parameter template, written in the placeholder form
 *
 * `T` is deliberately never defined as a real class: that is what makes the unspecialized
 * template reject every value, and what gives the engine a type name to substitute.
 *
 * @template T
 */
#[TemplateParameter('T')]
final class Box implements GenericObject
{
    use GenericTemplate;

    private ?T $value = null;

    public function set(T $value): void
    {
        $this->value = $value;
    }

    public function get(): ?T
    {
        return $this->value;
    }

    public function describe(): string
    {
        return static::class;
    }
}

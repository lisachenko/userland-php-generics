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

use Countable;
use Lisachenko\Generics\Attribute\TemplateParameter;
use Lisachenko\Generics\GenericObject;
use Lisachenko\Generics\GenericTemplate;

/**
 * A template whose type parameter is bounded, mirroring `@template T of Countable`
 */
#[TemplateParameter('T', of: Countable::class)]
final class Bounded implements GenericObject
{
    use GenericTemplate;

    private ?T $value = null;

    public function set(T $value): void
    {
        $this->value = $value;
    }
}

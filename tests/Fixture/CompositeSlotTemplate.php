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

/**
 * Puts the type parameter inside a union type, which the engine cannot substitute
 *
 * @template T
 */
#[TemplateParameter('T')]
final class CompositeSlotTemplate implements GenericObject
{
    private T|Payload|null $value = null;
}

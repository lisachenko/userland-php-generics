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
 * A template that declares a type parameter no declaration slot uses
 *
 * Perfectly legal, and worth covering: the specialization is still a distinct class with its
 * own statics and its own late static binding, it simply has no type to enforce.
 *
 * @template T
 */
#[TemplateParameter('T')]
final class Anything implements GenericObject
{
    use GenericTemplate;

    public function label(): string
    {
        return static::class;
    }
}

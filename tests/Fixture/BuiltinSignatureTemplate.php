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

use Lisachenko\Generics\Attribute\Of;
use Lisachenko\Generics\Attribute\TemplateParameter;
use Lisachenko\Generics\GenericObject;

/**
 * Marks a `mixed` parameter, which the engine could never enforce
 *
 * @template T
 */
#[TemplateParameter('T')]
final class BuiltinSignatureTemplate implements GenericObject
{
    public function set(#[Of('T')] mixed $value): void {}
}

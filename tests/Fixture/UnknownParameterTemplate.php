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
 * Names a type parameter the class never declared
 *
 * @template T
 */
#[TemplateParameter('T')]
final class UnknownParameterTemplate implements GenericObject
{
    #[Of('TOther')]
    private mixed $value = null;

    public function get(): mixed
    {
        return $this->value;
    }
}

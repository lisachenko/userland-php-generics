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
use Lisachenko\Generics\Attribute\OfReturn;
use Lisachenko\Generics\Attribute\TemplateParameter;
use Lisachenko\Generics\GenericObject;
use Lisachenko\Generics\GenericTemplate;

/**
 * The attribute form: slots announce their type parameter instead of naming it as their type
 *
 * The property is the case only this form can express - `mixed` has no type name for the
 * engine to key on. The signature slots here are declared with a placeholder type, so this
 * fixture covers the attribute mapping one placeholder class onto a differently-named type
 * parameter; BuiltinSignatureTemplate covers a `mixed` parameter, which needs the cached
 * ZEND_RECV mask patched and works too.
 *
 * @template TValue
 */
#[TemplateParameter('TValue')]
final class AttributeBox implements GenericObject
{
    use GenericTemplate;

    #[Of('TValue')]
    private mixed $value = null;

    public function set(#[Of('TValue')] T $value): void
    {
        $this->value = $value;
    }

    #[OfReturn('TValue')]
    public function get(): ?T
    {
        return $this->value;
    }
}

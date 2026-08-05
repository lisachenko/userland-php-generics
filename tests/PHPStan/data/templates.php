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

/*
 * Templates the rule tests analyse. Deliberately outside the PSR-4 layout so the autoloader
 * cannot load them: PHPStan reads them as source, and nothing else should see them.
 */

declare(strict_types=1);

namespace Lisachenko\Generics\PHPStan\Data;

use Countable;
use Lisachenko\Generics\Attribute\Of;
use Lisachenko\Generics\Attribute\OfReturn;
use Lisachenko\Generics\Attribute\TemplateParameter;
use Lisachenko\Generics\GenericObject;
use Lisachenko\Generics\GenericTemplate;

interface Container extends GenericObject {}

/** @template T */
#[TemplateParameter('T')]
class GoodBox implements Container
{
    use GenericTemplate;

    #[Of('T')]
    private mixed $value = null;

    public function set(#[Of('T')] mixed $value): void
    {
        $this->value = $value;
    }

    #[OfReturn('T')]
    public function get(): string
    {
        return (string) $this->value;
    }
}

/**
 * One tag per line: PHPStan parses only the first @template on a line, and a template whose
 * arity is misread analyses as a different generic type than it is
 *
 * @template TKey
 * @template TValue
 */
#[TemplateParameter('TKey')]
#[TemplateParameter('TValue')]
class GoodMap implements Container
{
    use GenericTemplate;

    #[Of('TValue')]
    private mixed $value = null;
}

/** @template T of Countable */
#[TemplateParameter('T', of: Countable::class)]
class BoundedBox implements Container
{
    use GenericTemplate;

    #[Of('T')]
    private mixed $value = null;
}

/** An ordinary class, not a template at all */
class NotATemplate {}

/** @template T */
class PhpstanOnlyGeneric {}

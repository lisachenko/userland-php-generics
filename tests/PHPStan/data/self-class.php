<?php

declare(strict_types=1);

namespace Lisachenko\Generics\PHPStan\Data\SelfClassCase;

use Lisachenko\Generics\Attribute\TemplateParameter;
use Lisachenko\Generics\PHPStan\Data\Container;

/** @template T */
#[TemplateParameter('T')]
class Describing implements Container
{
    public function wrong(): string
    {
        return self::class;     // reported: names the template
    }

    public function right(): string
    {
        return static::class;   // not reported: late-bound
    }
}

class Ordinary
{
    public function fine(): string
    {
        return self::class;     // not reported: not a template
    }
}

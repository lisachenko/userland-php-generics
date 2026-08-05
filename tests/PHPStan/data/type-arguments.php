<?php

declare(strict_types=1);

namespace Lisachenko\Generics\PHPStan\Data\TypeArgumentCase;

use Lisachenko\Generics\Generic;
use Lisachenko\Generics\PHPStan\Data\GoodBox;

function arguments(string $runtime): void
{
    GoodBox::of('int');                          // fine
    GoodBox::of(GoodBox::class);                 // fine
    GoodBox::of('iterable');                     // reported: no single type mask
    GoodBox::of('callable');                     // reported
    GoodBox::of('?int');                         // reported: nullability cannot be added
    GoodBox::of('int|string');                   // reported: no zend_type holds a union
    GoodBox::of($runtime);                       // not reported: not a literal

    Generic::specialize(GoodBox::class, 'int');       // fine
    Generic::specialize(GoodBox::class, 'resource');  // reported
}

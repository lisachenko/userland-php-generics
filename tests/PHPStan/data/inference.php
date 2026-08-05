<?php

declare(strict_types=1);

namespace Lisachenko\Generics\PHPStan\Data\InferenceCase;

use Lisachenko\Generics\Generic;
use Lisachenko\Generics\PHPStan\Data\GoodBox;
use Lisachenko\Generics\PHPStan\Data\GoodMap;

use function PHPStan\Testing\assertType;

function inference(string $runtime): void
{
    // The point of the whole extension: `new (expr)()` on a class-string<X> infers X
    assertType('Lisachenko\Generics\PHPStan\Data\GoodBox<int>', new (GoodBox::of('int'))());
    assertType('class-string<Lisachenko\Generics\PHPStan\Data\GoodBox<int>>', GoodBox::of('int'));
    assertType(
        'class-string<Lisachenko\Generics\PHPStan\Data\GoodBox<Lisachenko\Generics\PHPStan\Data\GoodBox<int>>>',
        GoodBox::of('Lisachenko\Generics\PHPStan\Data\GoodBox<int>'),
    );
    assertType(
        'class-string<Lisachenko\Generics\PHPStan\Data\GoodMap<string, int>>',
        GoodMap::of('string', 'int'),
    );

    assertType('class-string<Lisachenko\Generics\PHPStan\Data\GoodBox<int>>', Generic::specialize(GoodBox::class, 'int'));
    assertType('Lisachenko\Generics\PHPStan\Data\GoodBox<int>', Generic::new(GoodBox::class, ['int']));

    // Degradation: nothing resolvable, so the declared return type stands rather than a guess
    assertType('class-string<Lisachenko\Generics\PHPStan\Data\GoodBox>', GoodBox::of($runtime));
    assertType('class-string', Generic::specialize(GoodBox::class, $runtime));
    assertType('object', Generic::new(GoodBox::class, [$runtime]));

    // Wrong arity is the runtime's error to report; the extension must not invent a type
    assertType('class-string<Lisachenko\Generics\PHPStan\Data\GoodBox>', GoodBox::of('int', 'string'));
    assertType('class-string<Lisachenko\Generics\PHPStan\Data\GoodMap>', GoodMap::of('int'));
}

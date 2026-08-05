<?php

declare(strict_types=1);

namespace Lisachenko\Generics\PHPStan\Data\InstanceofCase;

use Lisachenko\Generics\PHPStan\Data\Container;
use Lisachenko\Generics\PHPStan\Data\GoodBox;
use Lisachenko\Generics\PHPStan\Data\NotATemplate;

function check(object $value): void
{
    // Reported: always false, because a specialization is a sibling of its template
    if ($value instanceof GoodBox) {
        echo 'never';
    }

    // Not reported: the interface rides onto every specialization
    if ($value instanceof Container) {
        echo 'fine';
    }

    // Not reported: an ordinary class
    if ($value instanceof NotATemplate) {
        echo 'fine';
    }
}

function caught(): void
{
    try {
        echo 'body';
    } catch (GoodBox $error) {          // reported
        echo 'never';
    }

    try {
        echo 'body';
    } catch (\RuntimeException $error) { // not reported
        echo 'fine';
    }
}

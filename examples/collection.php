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

namespace Lisachenko\Generics\Example;

use Lisachenko\Generics\Attribute\TemplateParameter;
use Lisachenko\Generics\Generic;
use Lisachenko\Generics\GenericObject;
use Lisachenko\Generics\GenericTemplate;
use TypeError;

/**
 * A specialization is a real class, and this is what "real" means
 *
 * Run it:
 *
 *     php -d ffi.enable=1 -d opcache.jit=off examples/collection.php
 *
 * Four things happen below, and the third is the one worth the trouble: the `TypeError` comes
 * from the Zend Engine, on a class that did not exist when this file started running, and it is
 * indistinguishable from the one a hand-written class would have thrown.
 */
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * @template T
 */
#[TemplateParameter('T')]
final class Collection implements GenericObject
{
    use GenericTemplate;

    /**
     * The element type is *not* enforced - `array` is all the engine can check here. See
     * docs/limitations.md; it is the one place in this package where less is checked than it
     * looks. `add()` below is where the real checking happens.
     *
     * @var list<T>
     */
    private array $items = [];

    public function add(T $item): static
    {
        $this->items[] = $item;

        return $this;
    }

    public function first(): ?T
    {
        return $this->items[0] ?? null;
    }

    public function count(): int
    {
        return count($this->items);
    }
}

final class Order
{
    public function __construct(public readonly string $reference) {}
}

Generic::bootstrap();

// 1. Specialize. `of()` returns a class name; `new (...)` is ordinary PHP 8 syntax.
$orders = new (Collection::of(Order::class))();

echo '1. get_class()            : ', $orders::class, PHP_EOL;
echo '   template               : ', Generic::templateOf($orders) ?? '?', PHP_EOL;
echo '   type arguments         : ', implode(', ', Generic::bindingOf($orders) ?? []), PHP_EOL;
echo PHP_EOL;

// 2. It behaves as the class you would have written by hand.
$orders->add(new Order('A-1'))->add(new Order('A-2'));

echo '2. count()                : ', $orders->count(), PHP_EOL;
echo '   first()->reference     : ', $orders->first()?->reference ?? '-', PHP_EOL;
echo PHP_EOL;

/*
 * 3. The point of the whole package. This TypeError is thrown by the engine, not by any code in
 *    this library - which is why nothing on the runtime path is allowed to catch one. Here it is
 *    caught only in order to print it.
 */
try {
    $orders->add('not an order');
    echo '3. UNEXPECTED             : the engine accepted a string', PHP_EOL;
} catch (TypeError $error) {
    echo '3. engine TypeError       : ', $error->getMessage(), PHP_EOL;
}
echo PHP_EOL;

/*
 * 4. The template is left exactly as it was. `T` is a class name that is never defined, so an
 *    unspecialized Collection accepts nothing at all - and a second specialization is a separate
 *    class with its own type, not a reconfiguration of the first.
 */
$strings = new (Collection::of('string'))();
$strings->add('this one wants strings');

echo '4. a second specialization: ', $strings::class, PHP_EOL;

try {
    (new Collection())->add(new Order('A-3'));
    echo '   UNEXPECTED             : the untouched template accepted an Order', PHP_EOL;
} catch (TypeError $error) {
    echo '   template untouched     : ', $error->getMessage(), PHP_EOL;
}

echo '   sibling, not subclass  : ',
    var_export($orders instanceof Collection, true),
    ' (instanceof is false by design - see docs/limitations.md)',
    PHP_EOL;

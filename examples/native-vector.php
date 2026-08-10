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

use Lisachenko\Generics\Exception\NativeVectorException;
use Lisachenko\Generics\Generic;
use Lisachenko\Generics\Native\NativeVector;
use TypeError;

/**
 * A block of memory you can index, whose storage is an ordinary PHP string
 *
 * Run it:
 *
 *     php -d ffi.enable=1 -d opcache.jit=off examples/native-vector.php
 *
 * The blob below stands in for anything that arrives as bytes - a file, a socket, a mmap'd
 * region. `pack()` builds it here because the example has to produce its own input; the vector
 * itself never packs or unpacks anything, which is the entire point: reading element 3 is a
 * bounds check and one `zend_long *` dereference into those very bytes.
 */
require_once __DIR__ . '/../vendor/autoload.php';

Generic::bootstrap();

$blob = pack('q*', 10, 20, 30, 40);

// 1. The cast. `NativeVector<int>` is a real class, minted here, whose element slots say `int`.
$samples = new (NativeVector::of('int'))($blob);

echo '1. get_class()            : ', $samples::class, PHP_EOL;
echo '   type arguments         : ', implode(', ', Generic::bindingOf($samples) ?? []), PHP_EOL;
echo '   count / bytes          : ', count($samples), ' / ', $samples->sizeInBytes(), PHP_EOL;
echo PHP_EOL;

// 2. Indexing reads and writes the block in place.
$samples[1] = -20;
$samples[]  = 50;
$samples->append(60);

echo '2. elements               : ', implode(', ', iterator_to_array($samples)), PHP_EOL;
echo '   element 3              : ', $samples->get(3), PHP_EOL;
echo PHP_EOL;

/*
 * 3. The element type is enforced by the engine, on a class that did not exist when this file
 *    started running. Caught here only in order to print it.
 */
try {
    $samples->append(1.5);
    echo '3. UNEXPECTED             : the engine accepted a float', PHP_EOL;
} catch (TypeError $error) {
    echo '3. engine TypeError       : ', $error->getMessage(), PHP_EOL;
}
echo PHP_EOL;

/*
 * 4. Back to a PHP string, byte for byte. The block is handed out rather than copied, so the
 *    next write separates it first and the value printed here cannot change afterwards.
 */
$roundTripped = $samples->toBinary();
$samples->set(0, 999);

echo '4. toBinary() round trip  : ', implode(', ', unpack('q*', $roundTripped)), PHP_EOL;
echo '   still the same bytes   : ', var_export($roundTripped === pack('q*', 10, -20, 30, 40, 50, 60), true), PHP_EOL;
echo '   vector moved on        : ', $samples->get(0), PHP_EOL;
echo PHP_EOL;

// 5. A second element kind is a second class, with `double` in the same slots.
$readings = new (NativeVector::of('float'))(pack('d*', 1.5, -2.25));
$readings->append(M_PI);

echo '5. a second specialization: ', $readings::class, PHP_EOL;
echo '   elements               : ', implode(', ', iterator_to_array($readings)), PHP_EOL;

try {
    $readings->append('not a double');
    echo '   UNEXPECTED             : the engine accepted a string', PHP_EOL;
} catch (TypeError $error) {
    echo '   engine TypeError       : ', $error->getMessage(), PHP_EOL;
}
echo PHP_EOL;

// 6. destroy() releases the block; it is idempotent, and the instance is done afterwards.
$samples->destroy();
$samples->destroy();

echo '6. destroyed count        : ', count($samples), PHP_EOL;

try {
    $samples->get(0);
    echo '   UNEXPECTED             : a destroyed vector still read an element', PHP_EOL;
} catch (NativeVectorException $error) {
    echo '   access after destroy   : ', $error->getMessage(), PHP_EOL;
}

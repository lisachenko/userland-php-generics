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

namespace Lisachenko\Generics\Integration;

use Lisachenko\Generics\Exception\TypeArgumentException;
use Lisachenko\Generics\Fixture\Bounded;
use Lisachenko\Generics\Fixture\Box;
use Lisachenko\Generics\Fixture\CountablePayload;
use Lisachenko\Generics\RequiresEngine;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Covers what may be passed as a type argument, and what the engine ends up storing
 *
 * Every canonical specialized name used here is used exactly once across the suite.
 */
final class TypeArgumentTest extends TestCase
{
    use RequiresEngine;

    public function testNestedGenericsResolveInnermostFirst(): void
    {
        $inner = Box::of('float');
        $outer = Box::of($inner);

        self::assertSame(Box::class . '<' . Box::class . '<float>>', $outer);

        // The outer slot stores nothing but the inner class name, which the engine resolves
        // lazily - so the inner specialization has to exist first, and it does.
        self::assertSame('?' . $inner, (string) (new ReflectionProperty($outer, 'value'))->getType());

        $box = new $outer();
        $box->set(new $inner());
        self::assertInstanceOf($inner, $box->get());
    }

    public function testNestedGenericsCanBeWrittenAsOneString(): void
    {
        $written = Box::of(Box::class . '<bool>');

        self::assertSame(Box::of(Box::of('bool')), $written);
    }

    public function testNestedGenericDepthIsLimited(): void
    {
        $this->expectException(TypeArgumentException::class);
        $this->expectExceptionMessage('exceeded the depth limit');

        Box::of(str_repeat(Box::class . '<', 9) . 'int' . str_repeat('>', 9));
    }

    public function testBoundIsSatisfiedByAConformingArgument(): void
    {
        $specialized = Bounded::of(CountablePayload::class);

        self::assertSame(
            '?' . CountablePayload::class,
            (string) (new ReflectionProperty($specialized, 'value'))->getType(),
        );
    }

    public function testBoundViolationIsRejected(): void
    {
        $this->expectException(TypeArgumentException::class);
        $this->expectExceptionMessage('is bound to Countable, which "int" does not satisfy');

        Bounded::of('int');
    }

    public function testNullableTypeArgumentIsRejected(): void
    {
        $this->expectException(TypeArgumentException::class);
        $this->expectExceptionMessage('cannot be nullable');

        Box::of('?int');
    }

    public function testUnionTypeArgumentIsRejected(): void
    {
        $this->expectException(TypeArgumentException::class);
        $this->expectExceptionMessage('union and intersection types');

        Box::of('int|string');
    }

    public function testUnknownNestedTemplateIsRejected(): void
    {
        $this->expectException(TypeArgumentException::class);
        $this->expectExceptionMessage('as a nested generic, but no such class exists');

        Box::of('Totally\\Missing\\Template<int>');
    }
}

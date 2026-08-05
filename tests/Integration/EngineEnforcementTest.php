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

use Lisachenko\Generics\EngineTestCase;
use Lisachenko\Generics\Exception\TemplateException;
use Lisachenko\Generics\Exception\TypeArgumentException;
use Lisachenko\Generics\Fixture\Anything;
use Lisachenko\Generics\Fixture\Box;
use Lisachenko\Generics\Fixture\Payload;
use Lisachenko\Generics\Generic;
use Lisachenko\Generics\GenericObject;
use ReflectionMethod;
use ReflectionProperty;
use TypeError;

/**
 * The tests that justify the whole package: a specialization's types are enforced by the
 * engine itself, and the template it was cloned from is left exactly as it was.
 *
 * Specializations stay registered in the class table for the rest of the process, so every
 * canonical name used here is used exactly once across the suite.
 */
final class EngineEnforcementTest extends EngineTestCase
{
    public function testSpecializationIsARealRegisteredClass(): void
    {
        $specialized = Box::of('int');

        self::assertSame(Box::class . '<int>', $specialized);
        self::assertTrue(class_exists($specialized, false));
    }

    public function testTemplateWithNoSubstitutableSlotsStillGetsItsOwnClass(): void
    {
        $specialized = Anything::of('int');
        $instance    = new $specialized();

        self::assertSame(Anything::class . '<int>', $specialized);
        self::assertSame($specialized, $instance->label());
        self::assertNotSame(Anything::class, $specialized);
    }

    public function testDeclaredTypesFollowTheSubstitutedType(): void
    {
        $specialized = Box::of('int');

        self::assertSame('?int', (string) (new ReflectionProperty($specialized, 'value'))->getType());
        self::assertSame('int', (string) (new ReflectionMethod($specialized, 'set'))->getParameters()[0]->getType());
        self::assertSame('?int', (string) (new ReflectionMethod($specialized, 'get'))->getReturnType());
    }

    public function testTheEngineEnforcesTheSubstitutedType(): void
    {
        $specialized = Box::of('int');
        $box         = new $specialized();

        $box->set(42);
        self::assertSame(42, $box->get());

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage(Box::class . '<int>::set()');
        $box->set('not an int');
    }

    public function testAClassNameCanBeTheTypeArgument(): void
    {
        $specialized = Box::of(Payload::class);
        $box         = new $specialized();

        self::assertSame(
            '?' . Payload::class,
            (string) (new ReflectionProperty($specialized, 'value'))->getType(),
        );

        $payload = new Payload('carried');
        $box->set($payload);
        self::assertSame($payload, $box->get());

        $this->expectException(TypeError::class);
        $box->set(42);
    }

    public function testTheTemplateIsLeftUntouched(): void
    {
        Box::of('float');

        // The placeholder is not a real class, so nothing at all is assignable on the template
        self::assertSame(
            '?Lisachenko\Generics\Fixture\T',
            (string) (new ReflectionProperty(Box::class, 'value'))->getType(),
        );

        $this->expectException(TypeError::class);
        (new Box())->set(1.5);
    }

    public function testEachTypeArgumentGetsItsOwnClass(): void
    {
        $ints    = Box::of('int');
        $strings = Box::of('string');

        self::assertNotSame($ints, $strings);
        self::assertSame('int', (string) (new ReflectionMethod($ints, 'set'))->getParameters()[0]->getType());
        self::assertSame('string', (string) (new ReflectionMethod($strings, 'set'))->getParameters()[0]->getType());
    }

    public function testSpecializationIsMemoized(): void
    {
        $first  = Box::of('bool');
        $before = Generic::factory()->cache()->count();
        $second = Box::of('bool');

        self::assertSame($first, $second);
        self::assertSame($before, Generic::factory()->cache()->count());
    }

    public function testArgumentSpellingIsNormalized(): void
    {
        self::assertSame(Box::of('int'), Box::of('INT'));
        self::assertSame(Box::of(Payload::class), Box::of('\\' . Payload::class));
    }

    public function testLateStaticBindingResolvesToTheSpecialization(): void
    {
        $specialized = Box::of('array');

        self::assertSame($specialized, (new $specialized())->describe());
        self::assertSame(Box::class, (new Box())->describe());
    }

    public function testSpecializationIsASiblingAndNotASubclass(): void
    {
        $specialized = Box::of('object');
        $box         = new $specialized();

        // Documented and deliberate: the copy shares the template's parent and interfaces,
        // it does not extend the template. The marker interface is what survives.
        self::assertNotInstanceOf(Box::class, $box);
        self::assertInstanceOf(GenericObject::class, $box);
    }

    public function testSpecializingASpecializationIsRejected(): void
    {
        $specialized = Box::of('null');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('already a specialization');
        $specialized::of('string');
    }

    public function testWrongNumberOfTypeArgumentsIsRejected(): void
    {
        $this->expectException(TypeArgumentException::class);
        $this->expectExceptionMessage('declares 1 type parameter(s) but 2 type argument(s) were given');

        Box::of('int', 'string');
    }

    public function testUnknownTypeArgumentIsRejected(): void
    {
        $this->expectException(TypeArgumentException::class);
        $this->expectExceptionMessage('neither a builtin type nor an existing class');

        Box::of('Totally\\Missing\\Type');
    }

    public function testUnsupportedBuiltinTypeArgumentIsRejected(): void
    {
        $this->expectException(TypeArgumentException::class);
        $this->expectExceptionMessage('union of array and Traversable');

        Box::of('iterable');
    }

    public function testGenericFacadeInstantiatesInOneStep(): void
    {
        $box = Generic::new(Box::class, ['string']);

        self::assertSame(Box::class . '<string>', get_class($box));
    }
}

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

use Lisachenko\Generics\Fixture\Box;
use Lisachenko\Generics\Fixture\Payload;
use Lisachenko\Generics\Generic;
use Lisachenko\Generics\GenericObject;
use PHPUnit\Framework\TestCase;

/**
 * Asking what a specialization is, given that `instanceof` cannot answer
 */
final class IdentityTest extends TestCase
{
    public function testASpecializationIsNotAnInstanceOfItsTemplate(): void
    {
        $box = new (Box::of(Payload::class))();

        // Asserted on purpose: this is the copy model, not a defect, and a change that made it
        // pass would mean the sibling relationship had silently become inheritance
        self::assertNotInstanceOf(Box::class, $box);

        // The one relation that does survive, which is why GenericObject is required
        self::assertInstanceOf(GenericObject::class, $box);
    }

    public function testTheHelpersAnswerWhatInstanceofCannot(): void
    {
        $box = new (Box::of('string'))();

        self::assertTrue(Generic::isSpecialization($box));
        self::assertTrue(Generic::isSpecialization($box, Box::class));
        self::assertSame(Box::class, Generic::templateOf($box));
        self::assertSame(['string'], Generic::bindingOf($box));
    }

    public function testTheHelpersAcceptANameAsWellAsAnInstance(): void
    {
        $specialized = Box::of('float');

        self::assertTrue(Generic::isSpecialization($specialized));
        self::assertSame(Box::class, Generic::templateOf($specialized));
        self::assertSame(['float'], Generic::bindingOf($specialized));
    }

    public function testANestedSpecializationReportsItsOuterBinding(): void
    {
        $inner = Generic::specialize(Box::class, 'int');
        $outer = Generic::specialize(Box::class, sprintf('%s<int>', Box::class));

        self::assertSame([$inner], Generic::bindingOf($outer));
        self::assertSame(Box::class, Generic::templateOf($outer));
    }

    public function testTheTemplateItselfIsNotASpecialization(): void
    {
        self::assertFalse(Generic::isSpecialization(Box::class));
        self::assertFalse(Generic::isSpecialization(new Box()));
        self::assertNull(Generic::templateOf(Box::class));
        self::assertNull(Generic::bindingOf(Box::class));
    }

    public function testAnUnrelatedClassIsNotASpecialization(): void
    {
        self::assertFalse(Generic::isSpecialization(new Payload()));
        self::assertNull(Generic::templateOf(Payload::class));
    }

    public function testNamingADifferentTemplateIsRejected(): void
    {
        $box = new (Box::of('bool'))();

        self::assertFalse(Generic::isSpecialization($box, Payload::class));
    }
}

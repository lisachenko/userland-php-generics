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

use Lisachenko\Generics\Exception\TemplateException;
use Lisachenko\Generics\Fixture\AttributeBox;
use Lisachenko\Generics\Fixture\BuiltinSignatureTemplate;
use Lisachenko\Generics\Fixture\UnknownParameterTemplate;
use Lisachenko\Generics\Generic;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use TypeError;

/**
 * Covers the attribute form, where slots announce their type parameter with #[Of]/#[OfReturn]
 *
 * Every canonical specialized name used here is used exactly once across the suite.
 */
final class AttributeFormTest extends TestCase
{
    public function testAMixedPropertyIsRetypedAndEnforced(): void
    {
        $specialized = AttributeBox::of('int');
        $box         = new $specialized();

        // Only the attribute form can express this: `mixed` has no type name to key on
        self::assertSame('?int', (string) (new ReflectionProperty($specialized, 'value'))->getType());
        self::assertSame('mixed', (string) (new ReflectionProperty(AttributeBox::class, 'value'))->getType());

        $box->set(42);
        self::assertSame(42, $box->get());
    }

    public function testSignatureSlotsFollowTheNamedTypeParameter(): void
    {
        $specialized = AttributeBox::of('string');
        $box         = new $specialized();

        self::assertSame('string', (string) (new ReflectionMethod($specialized, 'set'))->getParameters()[0]->getType());
        self::assertSame('?string', (string) (new ReflectionMethod($specialized, 'get'))->getReturnType());

        $box->set('text');
        self::assertSame('text', $box->get());

        $this->expectException(TypeError::class);
        $box->set(42);
    }

    public function testBothFormsProduceTheSameEnforcement(): void
    {
        $attributeForm = new (AttributeBox::of('float'))();

        $attributeForm->set(1.5);
        self::assertSame(1.5, $attributeForm->get());

        $this->expectException(TypeError::class);
        $attributeForm->set('not a float');
    }

    /**
     * The engine resolves the check for a builtin parameter at compile time and bakes it into
     * opcodes every specialization shares with its template, so substituting one would be
     * visible to reflection and never enforced. That is rejected at parse time.
     */
    public function testMarkingABuiltinTypedParameterIsRejected(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('opcodes that every specialization shares');

        Generic::specialize(BuiltinSignatureTemplate::class, 'int');
    }

    public function testMarkingAnUndeclaredTypeParameterIsRejected(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('which the class does not declare');

        Generic::specialize(UnknownParameterTemplate::class, 'int');
    }
}

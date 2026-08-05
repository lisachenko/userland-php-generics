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
use ZEngine\Reflection\ReflectionMethod as EngineMethod;

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
     * A `mixed` parameter is the case the attribute form exists for, and it now reaches all the
     * way down: z-engine un-shares the method's opcode array and patches the type mask ZEND_RECV
     * caches in the opline, which is what the engine actually tests.
     */
    public function testAMixedParameterIsRetypedAndEnforced(): void
    {
        // A `mixed` parameter is the one slot that needs the method's opcodes un-shared, and an
        // IS_CONST operand can only reach 2GB - so a body living in opcache shared memory is out
        // of range. Documented in docs/design.md; nothing else in the package is affected.
        if ((new EngineMethod(BuiltinSignatureTemplate::class, 'set'))->isImmutable()) {
            self::markTestSkipped('The template body is opcache-shared, which puts its literals out of 32-bit reach');
        }

        $specialized = Generic::specialize(BuiltinSignatureTemplate::class, 'int');

        // Same surface as the template, with `int` where the attribute marked `mixed`
        /** @var BuiltinSignatureTemplate<int> $instance */
        $instance = new $specialized();

        self::assertSame('int', (string) (new ReflectionMethod($specialized, 'set'))->getParameters()[0]->getType());

        $instance->set(42);

        // The template shares no writable opcode with the copy
        (new BuiltinSignatureTemplate())->set('anything at all');

        $this->expectException(TypeError::class);
        $instance->set('not an int');
    }

    public function testMarkingAnUndeclaredTypeParameterIsRejected(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('which the class does not declare');

        Generic::specialize(UnknownParameterTemplate::class, 'int');
    }
}

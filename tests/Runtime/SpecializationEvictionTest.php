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

namespace Lisachenko\Generics\Runtime;

use Lisachenko\Generics\Fixture\Box;
use Lisachenko\Generics\Fixture\BuiltinSignatureTemplate;
use Lisachenko\Generics\Fixture\Payload;
use Lisachenko\Generics\Generic;
use Lisachenko\Generics\RequiresEngine;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use TypeError;
use ZEngine\Core;
use ZEngine\Reflection\ReflectionMethod as EngineMethod;

/**
 * Destroying a specialization, which is the only test of the ownership contract
 *
 * Everything this package claims about memory rests on one arrangement: a specialization owns
 * its class entry, its `zend_property_info` copies and - where a slot needed it - a duplicated
 * `arg_info` block or an un-shared opcode array, while **sharing** the method bodies with its
 * template through the op_array refcount (docs/design.md sections 1 and 3).
 *
 * At request shutdown that arrangement is never really tested, because everything dies at once
 * and in an order the engine chooses. Deleting the class-table bucket runs `destroy_zend_class()`
 * over the copy **while the template is still live**, which is the one moment the two can be
 * told apart: if the copy released something the template still owns, the next line crashes.
 *
 * Destructive by construction, hence the group and the process isolation. On a debug build with
 * `report_memleaks=1` a block the copy failed to release fails the run as well.
 */
#[Group('internal')]
final class SpecializationEvictionTest extends TestCase
{
    use RequiresEngine;

    /**
     * A class-typed slot set: property, parameter and return type all substituted
     */
    public function testEvictingASpecializationLeavesItsTemplateIntact(): void
    {
        $specialized = Generic::specialize(Box::class, Payload::class);

        // Use it first, so the run-time cache and static-variable table the engine materializes
        // lazily per copy actually exist by the time it is destroyed
        $instance = new $specialized();
        $instance->set(new Payload());
        self::assertInstanceOf(Payload::class, $instance->get());
        unset($instance);

        $this->evict($specialized);

        // The load-bearing assertion. The template shares its method bodies with the class that
        // was just destroyed; if that teardown released them, this line does not return.
        $template = new Box();
        self::assertSame(Box::class, $template->describe());

        $type = (new ReflectionProperty(Box::class, 'value'))->getType();
        self::assertNotNull($type);
        self::assertStringContainsString('T', (string) $type);
    }

    /**
     * The sibling that un-shares an opcode array, which is the riskiest block to own
     *
     * A `mixed` parameter is the only slot whose specialization copies the method's opcodes into
     * request memory and rebases every `IS_CONST` operand against the literals it still shares
     * with the template. Destroying that copy has to free the copied opcodes without touching
     * the shared literals - the exact asymmetry docs/design.md section 3 describes.
     */
    public function testEvictingASpecializationThatUnsharedItsOpcodes(): void
    {
        if ((new EngineMethod(BuiltinSignatureTemplate::class, 'set'))->isImmutable()) {
            self::markTestSkipped('The template body is opcache-shared, so no opcode array is un-shared');
        }

        $specialized = Generic::specialize(BuiltinSignatureTemplate::class, 'int');

        $instance = new $specialized();
        $instance->set(7);
        self::assertSame(7, $instance->get());
        unset($instance);

        $this->evict($specialized);

        // The template kept its own opcodes and its own `mixed` parameter throughout
        $template = new BuiltinSignatureTemplate();
        $template->set('still anything at all');
        self::assertSame('still anything at all', $template->get());
    }

    /**
     * One sibling's teardown must not damage another's
     *
     * Two specializations of the same template each duplicated their own `arg_info` block from a
     * shared original. Freeing one of them incorrectly would leave the other reading released
     * memory - which shows up as the surviving class no longer enforcing its own type, rather
     * than as a crash.
     */
    public function testEvictingOneSiblingLeavesTheOtherEnforcing(): void
    {
        $ints    = Generic::specialize(Box::class, 'int');
        $strings = Generic::specialize(Box::class, 'string');

        $survivor = new $strings();
        $survivor->set('text');

        $this->evict($ints);

        self::assertSame(
            'string',
            (string) (new ReflectionMethod($strings, 'set'))->getParameters()[0]->getType(),
        );
        self::assertSame('text', $survivor->get());

        $this->expectException(TypeError::class);

        // Deliberately the wrong type - the engine rejecting it is the assertion. PHPStan knows
        // it is wrong because this package's own extension inferred `Box<string>` from the
        // specialize() call above, which is a pleasant way to find out the extension works.
        /** @phpstan-ignore argument.type */
        $survivor->set(42);
    }

    /**
     * Nothing dangling: the same arguments can be specialized again afterwards
     *
     * The cache adopts a name it finds in the class table, so re-specializing after an eviction
     * is also the test that the eviction really removed it rather than leaving a husk behind.
     */
    public function testTheSameSpecializationCanBeRebuiltAfterEviction(): void
    {
        $first = Generic::specialize(Box::class, 'float');
        $this->evict($first);

        Generic::reset();
        $second = Generic::specialize(Box::class, 'float');

        self::assertSame($first, $second);
        self::assertTrue(class_exists($second, false));

        $rebuilt = new $second();
        $rebuilt->set(1.5);
        self::assertSame(1.5, $rebuilt->get());
    }

    /**
     * Deletes the class-table bucket, running the full user-class teardown now
     *
     * `destroy_zend_class()` with refcount 1 - tables, own property infos and constants, owned
     * names - rather than at request shutdown where nothing can be distinguished.
     *
     * The assertions here are load-bearing rather than decorative: most of the tests below would
     * pass unchanged if this method quietly did nothing, since a class that was never destroyed
     * obviously cannot have damaged its template. Proving the teardown ran is what makes the
     * rest of the file mean anything.
     */
    private function evict(string $className): void
    {
        self::assertTrue(class_exists($className, false), 'nothing to evict - the test set itself up wrong');

        Core::$executor->classTable->delete(strtolower($className));

        self::assertFalse(class_exists($className, false), 'the class-table bucket was not removed');
    }
}

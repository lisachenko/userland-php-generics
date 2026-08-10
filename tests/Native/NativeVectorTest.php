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

namespace Lisachenko\Generics\Native;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Lisachenko\Generics\Exception\NativeVectorBoundsException;
use Lisachenko\Generics\Exception\NativeVectorException;
use Lisachenko\Generics\GenericObject;
use Lisachenko\Generics\RequiresEngine;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TypeError;

/**
 * The sole owner of every NativeVector specialization the suite mints
 *
 * `NativeVector<int>`, `NativeVector<float>` and `NativeVector<string>` are registered in the
 * class table for the rest of the process, so AGENTS.md section 6 makes this the only test
 * class allowed to name them. The shipped example uses the first two as well, which is why it
 * runs in a subprocess.
 *
 * `pack()` appears throughout as the *ground truth* the vector is checked against: the
 * production path never encodes or decodes anything, so an independent encoder is exactly what
 * a byte-level assertion needs. If the two ever disagree, one of them is wrong about the
 * machine's layout, and that is the assertion worth having.
 */
final class NativeVectorTest extends TestCase
{
    use RequiresEngine;

    /**
     * The two names this class owns, behind a helper each so the rule is visible in one place
     *
     * The element type is spelled out rather than inferred, and that is the documented way to
     * do it: a template described to PHPStan by a generated stub loses the `implements
     * GenericObject` the `of()` return-type extension keys on, so the analyser cannot derive
     * `NativeVector<int>` from `of('int')` on this class and must be told (docs/limitations.md,
     * "A stub-described template does not narrow `of()`"). Told once, here, every
     * `$vector->get(0)` below analyses as `int`.
     *
     * @return class-string<NativeVector<int>>
     */
    private static function ints(): string
    {
        /** @var class-string<NativeVector<int>> $specialized */
        $specialized = NativeVector::of('int');

        return $specialized;
    }

    /**
     * @return class-string<NativeVector<float>>
     */
    private static function floats(): string
    {
        /** @var class-string<NativeVector<float>> $specialized */
        $specialized = NativeVector::of('float');

        return $specialized;
    }

    /**
     * The same two classes, with the element type deliberately left unspoken
     *
     * The engine-rejection tests below pass values that are wrong on purpose. Told the element
     * type, PHPStan would report every one of them - correctly, and uselessly, because the
     * run-time rejection *is* the assertion. Told nothing, it stands back and lets the engine
     * do the work it is there to do.
     *
     * @return class-string<NativeVector<mixed>>
     */
    private static function unchecked(string $typeArgument): string
    {
        /** @var class-string<NativeVector<mixed>> $specialized */
        $specialized = NativeVector::of($typeArgument);

        return $specialized;
    }

    public function testASpecializationIsARealClassWithTheExpectedIdentity(): void
    {
        $vector = new (self::ints())();

        self::assertSame(NativeVector::class . '<int>', $vector::class);
        self::assertInstanceOf(GenericObject::class, $vector);
        self::assertInstanceOf(ArrayAccess::class, $vector);
        self::assertInstanceOf(Countable::class, $vector);
        self::assertInstanceOf(IteratorAggregate::class, $vector);
    }

    public function testABinaryBlockIsCastAndHandedBackUnchanged(): void
    {
        $blob   = pack('q*', 1, 2, 3, -4);
        $vector = (self::ints())::fromString($blob);

        self::assertCount(4, $vector);
        self::assertSame(32, $vector->sizeInBytes());
        self::assertSame($blob, $vector->toBinary());
    }

    public function testElementsAreReadAndWrittenThroughTheMethods(): void
    {
        $vector = (self::ints())::fromString(pack('q*', 10, 20, 30));

        self::assertSame(20, $vector->get(1));

        $vector->set(1, -20);
        self::assertSame(-20, $vector->get(1));
        self::assertSame(pack('q*', 10, -20, 30), $vector->toBinary());
    }

    public function testArraySyntaxDelegatesToTheSameSlots(): void
    {
        $vector = (self::ints())::fromString(pack('q*', 7, 8));

        self::assertSame(7, $vector[0]);

        $vector[1] = 99;
        $vector[]  = 100;

        self::assertSame(99, $vector[1]);
        self::assertSame(100, $vector[2]);
        self::assertSame(pack('q*', 7, 99, 100), $vector->toBinary());
    }

    public function testOffsetExistsAnswersForTheBlockAndNothingElse(): void
    {
        $vector = (self::ints())::fromString(pack('q*', 1, 2));

        self::assertTrue(isset($vector[0]));
        self::assertTrue(isset($vector[1]));
        self::assertFalse(isset($vector[2]));
        self::assertFalse(isset($vector[-1]));
        self::assertFalse(isset($vector['nope']));
    }

    public function testIterationYieldsEveryElementInOrder(): void
    {
        $vector = (self::ints())::fromString(pack('q*', 5, 6, 7));

        self::assertSame([5, 6, 7], iterator_to_array($vector));
    }

    public function testTheFullRangeOfANativeIntegerSurvivesTheRoundTrip(): void
    {
        $extremes = [PHP_INT_MIN, -1, 0, 1, PHP_INT_MAX];
        $vector   = (self::ints())::fromString(pack('q*', ...$extremes));

        self::assertSame($extremes, iterator_to_array($vector));
        self::assertSame(pack('q*', ...$extremes), $vector->toBinary());
    }

    public function testDoublesAreStoredAsDoubles(): void
    {
        $vector = (self::floats())::fromString(pack('d*', 1.5, -2.25, M_PI));

        self::assertSame(1.5, $vector->get(0));
        self::assertSame(-2.25, $vector->get(1));
        self::assertSame(M_PI, $vector->get(2));

        $vector->set(1, INF);
        $vector->append(-0.0);

        self::assertSame(pack('d*', 1.5, INF, M_PI, -0.0), $vector->toBinary());
    }

    public function testTheEngineRejectsAFloatInAVectorOfIntegers(): void
    {
        $vector = new (self::unchecked('int'))();

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('must be of type int, float given');
        $vector->append(1.5);
    }

    public function testTheEngineRejectsAStringWrittenThroughArraySyntax(): void
    {
        $vector = (self::unchecked('int'))::fromString(pack('q*', 1));

        // The sugar delegates, so the TypeError is raised on set() rather than on offsetSet()
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage(NativeVector::class . '<int>::set()');
        $vector[0] = 'not an int';
    }

    public function testTheEngineRejectsAStringInAVectorOfFloats(): void
    {
        $vector = new (self::unchecked('float'))();

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('must be of type float, string given');
        $vector->append('1.5');
    }

    public function testAnIntegerIsAcceptedByAVectorOfFloats(): void
    {
        // Not a hole in the enforcement: int-to-float widening is allowed by the language even
        // under strict_types, and what lands in the block is a double
        $vector = new (self::unchecked('float'))();
        $vector->append(3);

        self::assertSame(3.0, $vector->get(0));
        self::assertSame(pack('d', 3.0), $vector->toBinary());
    }

    public function testANegativeIndexIsOutOfBounds(): void
    {
        $vector = (self::ints())::fromString(pack('q*', 1, 2));

        $this->expectException(NativeVectorBoundsException::class);
        $this->expectExceptionMessage('Index -1 is outside the 2 element(s)');
        $vector->get(-1);
    }

    public function testTheIndexEqualToTheCountIsOutOfBounds(): void
    {
        $vector = (self::ints())::fromString(pack('q*', 1, 2));

        $this->expectException(NativeVectorBoundsException::class);
        $this->expectExceptionMessage('Index 2 is outside the 2 element(s)');
        $vector->set(2, 3);
    }

    public function testAnEmptyVectorHasNoValidIndexAtAll(): void
    {
        $vector = new (self::ints())();

        $this->expectException(NativeVectorBoundsException::class);
        $this->expectExceptionMessage('The vector is empty');
        $vector->get(0);
    }

    public function testAnElementCannotBeUnset(): void
    {
        $vector = (self::ints())::fromString(pack('q*', 1));

        $this->expectException(NativeVectorException::class);
        $this->expectExceptionMessage('cannot be unset');
        unset($vector[0]);
    }

    public function testABinaryHandedOutIsNotChangedByALaterWrite(): void
    {
        $vector   = (self::ints())::fromString(pack('q*', 1, 2, 3));
        $snapshot = $vector->toBinary();

        $vector->set(0, 999);

        // The block was shared the moment it was handed out, so the write separated first
        self::assertSame(pack('q*', 1, 2, 3), $snapshot);
        self::assertSame(pack('q*', 999, 2, 3), $vector->toBinary());
    }

    public function testASharedBinaryIsNotChangedByALaterAppendEither(): void
    {
        $vector   = (self::ints())::fromString(pack('q*', 4, 5));
        $snapshot = $vector->toBinary();

        $vector->append(6);

        self::assertSame(pack('q*', 4, 5), $snapshot);
        self::assertSame(pack('q*', 4, 5, 6), $vector->toBinary());
    }

    public function testTheStringACastWasMadeFromIsNeverWrittenInto(): void
    {
        // A literal is interned, which is the case that must never be written through
        $source = "\x01\x00\x00\x00\x00\x00\x00\x00";
        $vector = (self::ints())::fromString($source);

        $vector->set(0, 42);

        self::assertSame("\x01\x00\x00\x00\x00\x00\x00\x00", $source);
        self::assertSame(42, $vector->get(0));
    }

    public function testContentSurvivesTheReallocationsOfManyAppends(): void
    {
        $vector   = new (self::ints())();
        $expected = [];
        for ($index = 0; $index < 2_000; ++$index) {
            $value      = $index * -7;
            $expected[] = $value;
            $vector->append($value);
        }

        self::assertCount(2_000, $vector);
        self::assertSame($expected, iterator_to_array($vector));
        self::assertSame(pack('q*', ...$expected), $vector->toBinary());
    }

    public function testWithCapacityZeroFillsTheBlock(): void
    {
        $vector = (self::ints())::withCapacity(3);

        self::assertCount(3, $vector);
        self::assertSame([0, 0, 0], iterator_to_array($vector));
        self::assertSame(str_repeat("\0", 24), $vector->toBinary());
    }

    public function testWithCapacityRejectsANegativeCount(): void
    {
        $this->expectException(NativeVectorException::class);
        $this->expectExceptionMessage('no negative size');

        (self::ints())::withCapacity(-1);
    }

    public function testAppendFromStringGrowsTheBlockByAWholeBlob(): void
    {
        $vector = (self::ints())::fromString(pack('q*', 1));
        $vector->appendFromString(pack('q*', 2, 3));

        self::assertSame([1, 2, 3], iterator_to_array($vector));
        self::assertSame(pack('q*', 1, 2, 3), $vector->toBinary());
    }

    public function testAppendFromStringRejectsAMisalignedBlob(): void
    {
        $vector = new (self::ints())();

        $this->expectException(NativeVectorException::class);
        $this->expectExceptionMessage('byte(s) would be left over');
        $vector->appendFromString('abc');
    }

    public function testCastingAMisalignedBinaryIsRejected(): void
    {
        $this->expectException(NativeVectorException::class);
        $this->expectExceptionMessage('holds 8-byte elements');

        (self::ints())::fromString('abcdefghij');
    }

    public function testDestroyIsIdempotent(): void
    {
        $vector = (self::ints())::fromString(pack('q*', 1, 2));

        $vector->destroy();
        $vector->destroy();

        self::assertCount(0, $vector);
        self::assertSame(0, $vector->sizeInBytes());
        self::assertFalse(isset($vector[0]));
    }

    public function testReadingAfterDestroyThrows(): void
    {
        $vector = (self::ints())::fromString(pack('q*', 1, 2));
        $vector->destroy();

        $this->expectException(NativeVectorException::class);
        $this->expectExceptionMessage('released by destroy()');
        $vector->get(0);
    }

    public function testWritingAfterDestroyThrows(): void
    {
        $vector = (self::ints())::fromString(pack('q*', 1, 2));
        $vector->destroy();

        $this->expectException(NativeVectorException::class);
        $this->expectExceptionMessage('released by destroy()');
        $vector->append(3);
    }

    public function testTheBinaryOfADestroyedVectorCannotBeAskedForEither(): void
    {
        $vector = (self::ints())::fromString(pack('q*', 1));
        $vector->destroy();

        $this->expectException(NativeVectorException::class);
        $this->expectExceptionMessage('released by destroy()');
        $vector->toBinary();
    }

    public function testTheRawTemplateCannotBeConstructed(): void
    {
        $this->expectException(NativeVectorException::class);
        $this->expectExceptionMessage('cannot be constructed without a type argument');

        new NativeVector();
    }

    public function testATypeArgumentWithNoNativeLayoutIsRejectedAtConstruction(): void
    {
        // The specialization itself is legal - it is a real class with `string` in its slots -
        // and it is the layout that has no meaning, so this is the constructor's rejection
        $specialized = NativeVector::of('string');
        self::assertTrue(class_exists($specialized, false));

        $this->expectException(NativeVectorException::class);
        $this->expectExceptionMessage('can only hold "int" (a zend_long) or "float" (a double)');
        new $specialized();
    }

    public function testTheDeclaredElementTypesFollowTheTypeArgument(): void
    {
        $ints   = self::ints();
        $floats = self::floats();

        self::assertSame('int', (string) (new ReflectionMethod($ints, 'get'))->getReturnType());
        self::assertSame('int', (string) (new ReflectionMethod($ints, 'append'))->getParameters()[0]->getType());
        self::assertSame('float', (string) (new ReflectionMethod($floats, 'get'))->getReturnType());
        self::assertSame('float', (string) (new ReflectionMethod($floats, 'set'))->getParameters()[1]->getType());

        // ...and the index parameter was never a slot, so it is untouched
        self::assertSame('int', (string) (new ReflectionMethod($floats, 'set'))->getParameters()[0]->getType());
    }
}

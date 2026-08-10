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

namespace Lisachenko\Generics\StubGenerator;

use Lisachenko\Generics\Exception\TemplateException;
use Lisachenko\Generics\Fixture\AttributeBox;
use Lisachenko\Generics\Fixture\Box;
use Lisachenko\Generics\Fixture\NotATemplate;
use Lisachenko\Generics\Native\NativeVector;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The generator needs no engine: it reads declarations, it does not specialize anything
 */
final class StubGeneratorTest extends TestCase
{
    public function testAPlaceholderSlotBecomesMixedWithTheMeaningInADocTag(): void
    {
        $stub = (new StubGenerator())->generate(Box::class);

        // The whole point: the native type loses the fiction, the doc tag keeps the meaning
        self::assertStringContainsString('private mixed $value = null;', $stub->classDeclaration);
        self::assertStringContainsString('/** @var T|null */', $stub->classDeclaration);
        self::assertStringContainsString('@param T $value', $stub->classDeclaration);
        self::assertStringContainsString('public function set(mixed $value): void', $stub->classDeclaration);
        self::assertStringContainsString('@return T|null', $stub->classDeclaration);
    }

    /**
     * The placeholder form spells nullability in the native type it is about to replace, so a
     * generator that only read the slot would silently drop it
     */
    public function testNullabilitySurvivesTheRewrite(): void
    {
        $stub = (new StubGenerator())->generate(Box::class);

        self::assertStringContainsString('T|null', $stub->classDeclaration);
        self::assertStringNotContainsString('@var T ', $stub->classDeclaration);
    }

    public function testTheClassKeepsItsTemplateTags(): void
    {
        $stub = (new StubGenerator())->generate(Box::class);

        self::assertStringContainsString('@template T', $stub->classDeclaration);
    }

    /**
     * A stub cannot name the library's own traits: stub files are reflected before the
     * analysed paths are indexed
     */
    public function testOfIsWrittenOutRatherThanInherited(): void
    {
        $stub = (new StubGenerator())->generate(Box::class);

        self::assertStringContainsString('public static function of(string ...$typeArguments): string', $stub->classDeclaration);
        self::assertStringContainsString('@return class-string<Box<T>>', $stub->classDeclaration);
        self::assertStringNotContainsString('GenericTemplate', $stub->classDeclaration);
        self::assertStringNotContainsString('GenericObject', $stub->classDeclaration);
    }

    public function testTheAttributeFormNeedsNoPlaceholderDeclarations(): void
    {
        $placeholder = (new StubGenerator())->generate(Box::class)->placeholderNames;

        self::assertSame(['Lisachenko\\Generics\\Fixture\\T'], $placeholder);
    }

    public function testAttributeAndPlaceholderSlotsBothResolveToTheirTypeParameter(): void
    {
        $stub = (new StubGenerator())->generate(AttributeBox::class);

        // AttributeBox mixes the forms: a `mixed` property with #[Of], placeholder signatures
        self::assertStringContainsString('@template TValue', $stub->classDeclaration);
        self::assertStringContainsString('/** @var TValue|null */', $stub->classDeclaration);
        self::assertStringContainsString('@param TValue $value', $stub->classDeclaration);
    }

    public function testOneClassPerFile(): void
    {
        // Not a preference: PHPStan indexes only the first class declaration in a stub file
        $stub = (new StubGenerator())->generate(Box::class);

        self::assertSame('box-stub.php', $stub->fileName());
        self::assertSame(1, substr_count($stub->render(), 'class Box'));
    }

    /**
     * The shipped template is the first one with private members, named constructors and
     * interfaces, so it is what the tests from here on keep honest. None of them specializes anything:
     * the generator reads declarations, so naming the class here cannot collide with the
     * specializations NativeVectorTest owns.
     */
    public function testVisibilityIsReproducedRatherThanAssumed(): void
    {
        $stub = (new StubGenerator())->generate(NativeVector::class);

        // A stub that promoted a private helper to public would let analysed code call it
        self::assertStringContainsString('private function acquire(): \\FFI\\CData', $stub->classDeclaration);
        self::assertStringContainsString('public function get(int $index): mixed', $stub->classDeclaration);
    }

    public function testNamedConstructorsSurviveButOfIsStillWrittenOut(): void
    {
        $stub = (new StubGenerator())->generate(NativeVector::class);

        self::assertStringContainsString('public static function fromString(string $binary): static', $stub->classDeclaration);
        self::assertStringContainsString('public static function withCapacity(int $count): static', $stub->classDeclaration);

        // of() is reproduced once, by the generator, with the return type the extension narrows
        self::assertSame(1, substr_count($stub->classDeclaration, 'function of('));
    }

    public function testDefaultValuesSurviveSoAnOptionalParameterStaysOptional(): void
    {
        $stub = (new StubGenerator())->generate(NativeVector::class);

        // Without this, `new (NativeVector::of('int'))()` reads as passing too few arguments
        self::assertStringContainsString("public function __construct(string \$binary = '')", $stub->classDeclaration);
    }

    /**
     * `$vector[0]`, `count($vector)` and `foreach` are only legal in analysed code if the stub
     * says the class is an ArrayAccess, a Countable and an IteratorAggregate
     */
    public function testPhpsOwnInterfacesAreDeclaredAndTheLibrarysAreNot(): void
    {
        $stub = (new StubGenerator())->generate(NativeVector::class);

        self::assertStringContainsString(
            'final class NativeVector implements \\ArrayAccess, \\Countable, \\IteratorAggregate',
            $stub->classDeclaration,
        );

        // Traversable is implied by IteratorAggregate, and the library's own marker cannot be
        // named at all - a stub is reflected before the analysed paths are indexed
        self::assertStringNotContainsString('Traversable,', $stub->classDeclaration);
        self::assertStringNotContainsString('GenericObject', $stub->classDeclaration);
    }

    /**
     * A generic interface has to say what it was parameterized with, and only the template knows
     *
     * This is the one thing the generator reads out of a doc comment, and it is allowed to:
     * the prohibition in AGENTS.md decision 1 is on the *runtime* path, and doc comments are
     * where this package deliberately keeps everything static analysis needs. The skip is that
     * rule showing its edge - with `opcache.save_comments=0` there is no doc comment to read,
     * which is exactly why nothing on the runtime path may depend on one.
     */
    public function testGenericInterfacesAreParameterizedFromTheTemplatesOwnImplementsTags(): void
    {
        if ((new ReflectionClass(NativeVector::class))->getDocComment() === false) {
            self::markTestSkipped(
                'Doc comments are unavailable (opcache.save_comments=0), so the stub generator - '
                . 'which is tooling, not the runtime path - has nothing to read the @implements '
                . 'tags from. Regenerate stubs on a host that keeps doc comments.',
            );
        }

        $stub = (new StubGenerator())->generate(NativeVector::class);

        self::assertStringContainsString('@implements \\ArrayAccess<int, T>', $stub->classDeclaration);
        self::assertStringContainsString('@implements \\IteratorAggregate<int, T>', $stub->classDeclaration);
    }

    public function testClassConstantsArePartOfTheSurfaceAndAreReproduced(): void
    {
        $stub = (new StubGenerator())->generate(NativeVector::class);

        self::assertStringContainsString('public const ELEMENT_SIZE = 8;', $stub->classDeclaration);
    }

    public function testATemplateTheRuntimeWouldRejectIsRejectedHere(): void
    {
        // The generator parses through TemplateParser, so it accepts exactly what the runtime
        // accepts - reporting the problem at generation time is strictly earlier
        $this->expectException(TemplateException::class);

        (new StubGenerator())->generate(NotATemplate::class);
    }
}

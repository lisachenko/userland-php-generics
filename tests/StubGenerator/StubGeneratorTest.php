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
use PHPUnit\Framework\TestCase;

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

    public function testATemplateTheRuntimeWouldRejectIsRejectedHere(): void
    {
        // The generator parses through TemplateParser, so it accepts exactly what the runtime
        // accepts - reporting the problem at generation time is strictly earlier
        $this->expectException(TemplateException::class);

        (new StubGenerator())->generate(NotATemplate::class);
    }
}

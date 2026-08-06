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

namespace Lisachenko\Generics\Naming;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The identifier-safe alternative, including where it stops being exact
 *
 * The degradation is asserted rather than glossed over: this mangler buys a legal PHP
 * identifier by spending the property that makes the angle-bracket name safe, and a test that
 * only covered the cases where it happens to round-trip would hide the price.
 */
final class IdentifierSafeNameManglerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, string}>
     */
    public static function names(): iterable
    {
        yield 'one builtin' => ['App\\Box', ['int'], 'App\\Generic\\Box_int'];
        yield 'two builtins' => ['App\\Map', ['string', 'int'], 'App\\Generic\\Map_string_int'];
        yield 'global namespace' => ['Box', ['int'], 'Generic\\Box_int'];
        yield 'class argument' => ['App\\Box', ['App\\User'], 'App\\Generic\\Box_App_User'];
        yield 'nested argument' => ['App\\Box', ['App\\Generic\\Box_int'], 'App\\Generic\\Box_App_Generic_Box_int'];
    }

    /**
     * @param list<string> $typeArguments
     */
    #[DataProvider('names')]
    public function testItProducesALegalIdentifier(string $templateName, array $typeArguments, string $expected): void
    {
        /** @var class-string $templateName */
        $mangled = (new IdentifierSafeNameMangler())->mangle($templateName, $typeArguments);

        self::assertSame($expected, $mangled);
        self::assertMatchesRegularExpression('/^(?:[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*\\\\?)+$/', $mangled);
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function unambiguousNames(): iterable
    {
        yield 'one builtin' => ['App\\Box', ['int']];
        yield 'two builtins' => ['App\\Map', ['string', 'int']];
        yield 'global namespace' => ['Box', ['int']];
    }

    /**
     * @param list<string> $typeArguments
     */
    #[DataProvider('unambiguousNames')]
    public function testItRunsBackwardsWhileNoNameContainsASeparator(string $templateName, array $typeArguments): void
    {
        $mangler = new IdentifierSafeNameMangler();
        /** @var class-string $templateName */
        $parsed = $mangler->parse($mangler->mangle($templateName, $typeArguments));

        self::assertNotNull($parsed);
        self::assertSame($templateName, $parsed->templateName);
        self::assertSame($typeArguments, $parsed->typeArguments);
    }

    /**
     * The price of identifier safety, stated as a test rather than as a warning
     *
     * A class-typed argument has its namespace separators flattened into the same `_` that
     * separates the arguments, so the name no longer says where one ends and the next begins.
     * `SpecializationRegistry` is what answers exactly for a name this process minted; this is
     * only what is left for a name it did not.
     *
     * @return iterable<string, array{string}>
     */
    public static function lossyNames(): iterable
    {
        yield 'a class-typed argument' => ['App\\Box'];
    }

    /**
     * @see lossyNames
     */
    #[DataProvider('lossyNames')]
    public function testItCannotRunBackwardsThroughAFlattenedClassName(string $templateName): void
    {
        $mangler = new IdentifierSafeNameMangler();
        /** @var class-string $templateName */
        $parsed = $mangler->parse($mangler->mangle($templateName, ['App\\User']));

        self::assertNotNull($parsed);
        self::assertSame('App\\Box', $parsed->templateName);

        // What was one class argument reads back as two, because `\` and `,` became the same
        // character. Asserted so that a change which claims to fix it has to prove it.
        self::assertSame(['App', 'User'], $parsed->typeArguments);
        self::assertSame(2, $parsed->arity());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignNames(): iterable
    {
        yield 'an ordinary class' => ['App\\Box'];
        yield 'an ordinary class with an underscore' => ['App\\Box_Of_Things'];
        yield 'the right namespace, no arguments' => ['App\\Generic\\Box'];
        yield 'a nested namespace that merely ends in Generic' => ['App\\Generics\\Box_int'];
        yield 'an angle-bracket name' => ['App\\Box<int>'];
    }

    #[DataProvider('foreignNames')]
    public function testANameItDidNotProduceIsNotRecognised(string $className): void
    {
        $mangler = new IdentifierSafeNameMangler();

        self::assertFalse($mangler->isMangled($className));
        self::assertNull($mangler->parse($className));
    }

    /**
     * The collision the class docblock warns about, demonstrated
     *
     * Nothing stops somebody declaring `App\Generic\Box_int` by hand, and this mangler has no
     * way to tell the two apart. That is exactly the guarantee `AngleBracketNameMangler` keeps
     * and this one gives up, which is why it is opt-in.
     */
    public function testAHandWrittenNameInThatNamespaceIsIndistinguishable(): void
    {
        $mangler = new IdentifierSafeNameMangler();

        self::assertTrue($mangler->isMangled('App\\Generic\\Box_int'));
        self::assertSame('App\\Box', $mangler->parse('App\\Generic\\Box_int')?->templateName);
    }
}

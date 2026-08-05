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
 * The mangler has to run backwards as well as forwards
 *
 * `templateOf()` and `bindingOf()` are the only way to ask what a specialization is, since
 * `instanceof` against the template is permanently false - so a name that cannot be taken
 * apart again is a name that has lost information.
 */
final class AngleBracketNameManglerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function names(): iterable
    {
        yield 'one builtin' => ['App\\Box', ['int']];
        yield 'one class' => ['App\\Box', ['App\\User']];
        yield 'two arguments' => ['App\\Map', ['string', 'App\\User']];
        yield 'nested' => ['App\\Box', ['App\\Box<int>']];
        yield 'nested with siblings' => ['App\\Map', ['string', 'App\\Map<int,App\\Box<float>>']];
        yield 'nullable argument' => ['App\\Box', ['?int']];
    }

    /**
     * @param list<string> $typeArguments
     */
    #[DataProvider('names')]
    public function testMangledNamesRoundTrip(string $templateName, array $typeArguments): void
    {
        $mangler = new AngleBracketNameMangler();
        /** @var class-string $templateName */
        $parsed = $mangler->parse($mangler->mangle($templateName, $typeArguments));

        self::assertNotNull($parsed);
        self::assertSame($templateName, $parsed->templateName);
        self::assertSame($typeArguments, $parsed->typeArguments);
        self::assertSame(count($typeArguments), $parsed->arity());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignNames(): iterable
    {
        yield 'an ordinary class' => ['App\\Box'];
        yield 'unbalanced open' => ['App\\Box<int'];
        yield 'unbalanced close' => ['App\\Box<Box<int>'];
        yield 'stray close inside' => ['App\\Box<int>>'];
        yield 'no template name' => ['<int>'];
        yield 'empty argument' => ['App\\Box<>'];
        yield 'empty argument among others' => ['App\\Map<int,>'];
    }

    /**
     * A name this mangler did not produce is not an error - it is simply not ours, and every
     * caller of parse() branches on null rather than catching something
     */
    #[DataProvider('foreignNames')]
    public function testANameItDidNotProduceIsNotParsed(string $className): void
    {
        self::assertNull((new AngleBracketNameMangler())->parse($className));
    }
}

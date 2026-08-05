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

namespace Lisachenko\Generics\Type;

use Lisachenko\Generics\Exception\TypeArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers the small type grammar; no engine and no reflection involved
 */
final class TypeArgumentParserTest extends TestCase
{
    private TypeArgumentParser $parser;

    protected function setUp(): void
    {
        $this->parser = new TypeArgumentParser();
    }

    public function testParsesAPlainType(): void
    {
        $argument = $this->parser->parse('Tpl', 'int');

        self::assertSame('int', $argument->name);
        self::assertFalse($argument->nullable);
        self::assertFalse($argument->isGeneric());
    }

    public function testStripsTheLeadingBackslashAndSurroundingSpace(): void
    {
        self::assertSame('App\\User', $this->parser->parse('Tpl', '  \\App\\User ')->name);
    }

    public function testParsesNullability(): void
    {
        $argument = $this->parser->parse('Tpl', '?App\\User');

        self::assertTrue($argument->nullable);
        self::assertSame('App\\User', $argument->name);
    }

    public function testParsesNestedArguments(): void
    {
        $argument = $this->parser->parse('Tpl', 'App\\Map<string,App\\Box<int>>');

        self::assertSame('App\\Map', $argument->name);
        self::assertCount(2, $argument->arguments);
        self::assertSame('string', $argument->arguments[0]->name);
        self::assertSame('App\\Box', $argument->arguments[1]->name);
        self::assertSame('int', $argument->arguments[1]->arguments[0]->name);
    }

    public function testRoundTripsThroughToString(): void
    {
        self::assertSame('?App\\Map<string,App\\Box<int>>', $this->parser->parse(
            'Tpl',
            '?\\App\\Map< string , App\\Box<int> >',
        )->toString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedArguments(): iterable
    {
        yield 'empty' => ['', 'it is empty'];
        yield 'blank' => ['   ', 'it is empty'];
        yield 'union' => ['int|string', 'union and intersection types'];
        yield 'intersection' => ['Countable&Traversable', 'union and intersection types'];
        yield 'unclosed bracket' => ['App\\Box<int', 'unbalanced "<"'];
        yield 'unopened bracket' => ['App\\Box>', 'unbalanced ">"'];
        yield 'nested unbalanced' => ['App\\Map<App\\Box<int>', 'unbalanced'];
        yield 'empty nested name' => ['App\\Box<>', 'it is empty'];
        yield 'empty outer name' => ['<int>', 'it has an empty type name'];
    }

    #[DataProvider('malformedArguments')]
    public function testRejectsMalformedArguments(string $argument, string $expectedMessage): void
    {
        $this->expectException(TypeArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->parser->parse('Tpl', $argument);
    }
}

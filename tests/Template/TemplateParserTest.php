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

namespace Lisachenko\Generics\Template;

use Lisachenko\Generics\Exception\TemplateException;
use Lisachenko\Generics\Fixture\Box;
use Lisachenko\Generics\Fixture\CompositeSlotTemplate;
use Lisachenko\Generics\Fixture\NotATemplate;
use Lisachenko\Generics\Fixture\UnmarkedTemplate;
use PHPUnit\Framework\TestCase;

/**
 * Covers turning a template class into a TemplateDefinition; no engine involved
 */
final class TemplateParserTest extends TestCase
{
    private TemplateParser $parser;

    protected function setUp(): void
    {
        $this->parser = new TemplateParser();
    }

    public function testParsesDeclaredParametersInOrder(): void
    {
        $definition = $this->parser->parse(Box::class);

        self::assertSame(Box::class, $definition->className);
        self::assertSame(['T'], $definition->parameterNames());
        self::assertSame(1, $definition->arity());
    }

    public function testDiscoversEveryPlaceholderSlot(): void
    {
        $definition = $this->parser->parse(Box::class);
        $described  = array_map(
            static fn(SlotDefinition $slot): string => $slot->describe('Box'),
            $definition->slotsFor('T'),
        );

        self::assertSame([
            'property Box::$value',
            'parameter #0 ($value) of Box::set()',
            'return type of Box::get()',
        ], $described);
    }

    public function testResolvesThePlaceholderToItsFullyQualifiedName(): void
    {
        $slots = $this->parser->parse(Box::class)->slotsFor('T');

        self::assertSame(SlotForm::Placeholder, $slots[0]->form);
        self::assertSame('Lisachenko\Generics\Fixture\T', $slots[0]->declaredTypeName);
    }

    public function testMethodsWithoutPlaceholdersProduceNoSlots(): void
    {
        $definition = $this->parser->parse(Box::class);
        $members    = array_map(
            static fn(SlotDefinition $slot): string => $slot->memberName,
            $definition->slots,
        );

        self::assertNotContains('describe', $members);
    }

    public function testClassWithoutTemplateParametersIsRejected(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('is not a generic template');

        $this->parser->parse(NotATemplate::class);
    }

    public function testTemplateWithoutTheMarkerInterfaceIsRejected(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('must implement');

        $this->parser->parse(UnmarkedTemplate::class);
    }

    public function testPlaceholderInsideAUnionTypeIsRejected(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('union or intersection type');

        $this->parser->parse(CompositeSlotTemplate::class);
    }
}

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

use PHPUnit\Framework\TestCase;

/**
 * The record of what was made, independent of any engine
 *
 * Nothing here boots z-engine on purpose: the registry is bookkeeping, and keeping it that way
 * is what lets `bindingOf()` answer without re-reading a name.
 */
final class SpecializationRegistryTest extends TestCase
{
    public function testItAnswersForANameItRecorded(): void
    {
        $registry = new SpecializationRegistry();
        $registry->record(self::binding(['int'], 'App\\Box<int>'));

        $binding = $registry->bindingFor('App\\Box<int>');

        self::assertNotNull($binding);
        self::assertSame('App\\Box', $binding->templateName);
        self::assertSame(['int'], $binding->typeArguments);
        self::assertSame('App\\Box<int>', $binding->specializedName);
        self::assertSame(1, $binding->arity());
    }

    public function testItSaysNothingAboutANameItNeverSaw(): void
    {
        self::assertNull((new SpecializationRegistry())->bindingFor('App\\Box<int>'));
    }

    public function testRecordingTheSameNameTwiceKeepsOneEntry(): void
    {
        $registry = new SpecializationRegistry();
        $registry->record(self::binding(['int'], 'App\\Box<int>'));
        $registry->record(self::binding(['int'], 'App\\Box<int>'));

        self::assertSame(1, $registry->count());
    }

    public function testItKeepsTheOrderThingsWereMadeIn(): void
    {
        $registry = new SpecializationRegistry();
        $registry->record(self::binding(['int'], 'App\\Box<int>'));
        $registry->record(self::binding(['string'], 'App\\Box<string>'));

        self::assertSame(
            ['App\\Box<int>', 'App\\Box<string>'],
            array_map(static fn(TypeBinding $binding): string => $binding->specializedName, $registry->all()),
        );
    }

    public function testForgettingClearsTheRecordsAndNothingElse(): void
    {
        $registry = new SpecializationRegistry();
        $registry->record(self::binding(['int'], 'App\\Box<int>'));
        $registry->forget();

        self::assertSame(0, $registry->count());
        self::assertSame([], $registry->all());
        self::assertNull($registry->bindingFor('App\\Box<int>'));
    }

    /**
     * A record for the one template these tests use
     *
     * The names are strings and nothing here loads a class: the registry stores what it is told
     * and never asks the engine anything, which is the property being tested.
     *
     * @param list<string> $typeArguments
     */
    private static function binding(
        array $typeArguments,
        string $specializedName,
        string $templateName = 'App\\Box',
    ): TypeBinding {
        /** @var class-string $templateName */
        /** @var class-string $specializedName */
        return TypeBinding::of($templateName, $typeArguments, $specializedName);
    }
}

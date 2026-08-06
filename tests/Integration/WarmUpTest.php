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

use Lisachenko\Generics\Fixture\Anything;
use Lisachenko\Generics\Fixture\Bounded;
use Lisachenko\Generics\Fixture\Box;
use Lisachenko\Generics\Fixture\CountablePayload;
use Lisachenko\Generics\Fixture\NotATemplate;
use Lisachenko\Generics\GenericFactory;
use Lisachenko\Generics\Naming\IdentifierSafeNameMangler;
use Lisachenko\Generics\RequiresEngine;
use PHPUnit\Framework\TestCase;

/**
 * Paying for specializations at boot, and the record that says what they are
 *
 * Minting is a start-up cost and looking one up again is not (docs/benchmarks.md), which is the
 * entire argument for warm-up. The registry is the other half: it remembers what each name was
 * made from, so the answer does not depend on the name being readable backwards.
 */
final class WarmUpTest extends TestCase
{
    use RequiresEngine;

    public function testWarmUpMintsEverythingItIsGiven(): void
    {
        $specialized = (new GenericFactory())->warmUp([
            [Box::class, [CountablePayload::class]],
            [Box::class, [NotATemplate::class]],
        ]);

        self::assertCount(2, $specialized);
        foreach ($specialized as $className) {
            self::assertTrue(class_exists($className, false));
        }
    }

    public function testWarmUpReturnsTheNamesInTheOrderItWasGiven(): void
    {
        $factory     = new GenericFactory();
        $specialized = $factory->warmUp([
            [Anything::class, [CountablePayload::class]],
            [Anything::class, [NotATemplate::class]],
        ]);

        self::assertSame(
            [
                $factory->specialize(Anything::class, CountablePayload::class),
                $factory->specialize(Anything::class, NotATemplate::class),
            ],
            $specialized,
        );
    }

    public function testResetClearsTheCacheAndLeavesTheClassRegistered(): void
    {
        $factory     = new GenericFactory();
        $specialized = $factory->specialize(Box::class, Anything::class);

        self::assertSame(1, $factory->cache()->count());
        self::assertSame(1, $factory->registry()->count());

        $factory->reset();

        self::assertSame(0, $factory->cache()->count());
        self::assertSame(0, $factory->registry()->count());

        // The class table is engine state and reset() does not touch it, so asking again adopts
        // the class that is still there rather than trying to build a second one
        self::assertTrue(class_exists($specialized, false));
        self::assertSame($specialized, $factory->specialize(Box::class, Anything::class));
        self::assertSame(1, $factory->registry()->count());
    }

    public function testTheRegistryRecordsWhatEachSpecializationWasMadeFrom(): void
    {
        $factory     = new GenericFactory();
        $specialized = $factory->specialize(Box::class, Bounded::class);

        $binding = $factory->registry()->bindingFor($specialized);

        self::assertNotNull($binding);
        self::assertSame(Box::class, $binding->templateName);
        self::assertSame([Bounded::class], $binding->typeArguments);
        self::assertSame($specialized, $binding->specializedName);
    }

    /**
     * The reason the registry exists rather than always re-reading the name
     *
     * `IdentifierSafeNameMangler` flattens a class argument's separators, so parsing its output
     * reports two arguments where one was given. The registry was written when the class was
     * made, so it still answers with the argument that was actually used.
     */
    public function testTheRegistryIsExactWhereParsingTheNameIsNot(): void
    {
        $factory     = new GenericFactory(mangler: new IdentifierSafeNameMangler());
        $specialized = $factory->specialize(Box::class, CountablePayload::class);

        self::assertSame([CountablePayload::class], $factory->bindingOf($specialized));
        self::assertSame(Box::class, $factory->templateOf($specialized));
        self::assertTrue($factory->isSpecialization($specialized, Box::class));

        // What is left for a name this process did not mint: the mangler's best effort, which
        // for this mangler is documented to be lossy - the one class argument reads back as the
        // four identifier-safe fragments its name was flattened into
        $parsed = $factory->mangler()->parse($specialized);

        self::assertNotNull($parsed);
        self::assertSame(4, $parsed->arity());
    }

    public function testTheIdentifierSafeNameIsAValidPhpIdentifier(): void
    {
        $factory     = new GenericFactory(mangler: new IdentifierSafeNameMangler());
        $specialized = $factory->specialize(Anything::class, NotATemplate::class);

        self::assertMatchesRegularExpression(
            '/^(?:[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*\\\\?)+$/',
            $specialized,
        );
        self::assertTrue(class_exists($specialized, false));
    }
}

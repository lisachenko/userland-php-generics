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
use Lisachenko\Generics\Fixture\Box;
use Lisachenko\Generics\GenericFactory;
use Lisachenko\Generics\Naming\IdentifierSafeNameMangler;
use Lisachenko\Generics\RequiresEngine;
use Lisachenko\Generics\Runtime\GenericAutoloader;
use PHPUnit\Framework\TestCase;

/**
 * Turning "class not found" into "here is the class", and the hard limit on when that is possible
 *
 * The first test is the important one, and it is about PHP rather than about this package: the
 * engine consults the autoload stack only for names that are valid class names, so the
 * angle-bracket name - the very thing that makes a specialization impossible to collide with -
 * can never be autoloaded by anything. That is why this autoloader ships alongside
 * `IdentifierSafeNameMangler` rather than on its own.
 *
 * Every test installs the autoloader and takes it back out again, which is also the assertion
 * that `unregister()` works: a leaked global autoloader would change the behaviour of every test
 * that runs after this one.
 */
final class AutoloaderTest extends TestCase
{
    use RequiresEngine;

    private ?GenericAutoloader $autoloader = null;

    protected function tearDown(): void
    {
        $this->autoloader?->unregister();
        $this->autoloader = null;
    }

    /**
     * Not a limitation of this package, and asserted so that nobody rediscovers it the hard way
     */
    public function testPhpNeverAutoloadsANameThatIsNotAValidClassName(): void
    {
        $seen  = [];
        $probe = static function (string $className) use (&$seen): void {
            $seen[] = $className;
        };
        spl_autoload_register($probe);

        try {
            self::assertFalse(class_exists('Lisachenko\\Generics\\Fixture\\Box<int>'));
            self::assertFalse(class_exists('Lisachenko\\Generics\\Fixture\\Absent'));
        } finally {
            spl_autoload_unregister($probe);
        }

        // The valid name reached the autoload stack; the angle-bracket one never did
        self::assertSame(['Lisachenko\\Generics\\Fixture\\Absent'], $seen);
    }

    public function testNothingIsAutoloadedUntilItIsRegistered(): void
    {
        self::assertFalse(class_exists($this->identifierSafeName(Box::class, 'true')));
    }

    public function testARegisteredAutoloaderMaterializesASpecializationByName(): void
    {
        $factory = new GenericFactory(mangler: new IdentifierSafeNameMangler());
        $name    = $this->identifierSafeName(Box::class, 'false');

        $this->autoloader = GenericAutoloader::register($factory);

        self::assertTrue(class_exists($name));
        self::assertInstanceOf($name, new $name());
        self::assertSame(['false'], $factory->bindingOf($name));
    }

    public function testItUsesTheFactoryItWasGiven(): void
    {
        $factory = new GenericFactory(mangler: new IdentifierSafeNameMangler());
        $name    = $this->identifierSafeName(Anything::class, 'true');

        $this->autoloader = GenericAutoloader::register($factory);

        self::assertTrue(class_exists($name));
        self::assertSame(Anything::class, $factory->templateOf($name));
        self::assertSame(1, $factory->registry()->count());
    }

    public function testAnOrdinaryMissingClassIsLeftToEverybodyElse(): void
    {
        $this->autoloader = GenericAutoloader::register(
            new GenericFactory(mangler: new IdentifierSafeNameMangler()),
        );

        self::assertFalse(class_exists('Totally\\Missing\\Klass'));
    }

    public function testANameWhoseTemplateDoesNotExistIsNotOurs(): void
    {
        $this->autoloader = GenericAutoloader::register(
            new GenericFactory(mangler: new IdentifierSafeNameMangler()),
        );

        self::assertFalse(class_exists('Totally\\Missing\\Generic\\Template_int'));
    }

    /**
     * A name that looks like ours but cannot be built is somebody else's class
     *
     * `IdentifierSafeNameMangler` flattens a class argument into fragments, so this name reads
     * back as four arguments for a one-parameter template. Throwing here would mean an unrelated
     * `class_exists()` in a `Generic` namespace could blow up, so the answer is a plain `false` -
     * the same one you would have got without the autoloader installed.
     */
    public function testANameItCannotBuildIsAnsweredWithFalseRatherThanAnException(): void
    {
        $this->autoloader = GenericAutoloader::register(
            new GenericFactory(mangler: new IdentifierSafeNameMangler()),
        );

        self::assertFalse(class_exists($this->identifierSafeName(Box::class, 'App\\Missing')));
    }

    public function testUnregisteringPutsThingsBackAsTheyWere(): void
    {
        $autoloader = GenericAutoloader::register(
            new GenericFactory(mangler: new IdentifierSafeNameMangler()),
        );

        self::assertTrue($autoloader->isRegistered());

        $autoloader->unregister();

        self::assertFalse($autoloader->isRegistered());
        self::assertFalse(class_exists($this->identifierSafeName(Box::class, 'null')));
    }

    /**
     * @param class-string $templateName
     */
    private function identifierSafeName(string $templateName, string $typeArgument): string
    {
        return (new IdentifierSafeNameMangler())->mangle($templateName, [$typeArgument]);
    }
}

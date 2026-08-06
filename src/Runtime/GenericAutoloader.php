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

use Lisachenko\Generics\Exception\GenericsException;
use Lisachenko\Generics\Generic;
use Lisachenko\Generics\GenericFactory;

/**
 * Materializes a specialization on demand, when something asks for it by name
 *
 * **Opt-in, and never installed for you.** Registering a global autoloader is a side effect a
 * library has no business imposing, so this one only exists once somebody calls `register()`.
 *
 * ## It only works with an identifier-safe mangler, and that is not a choice this package made
 *
 * PHP calls autoloaders only for names that are **valid class names** - a label, optionally with
 * namespace separators. Anything else is rejected before the autoload stack is consulted, so no
 * autoloader in the process ever sees it:
 *
 * ```php
 * spl_autoload_register(fn ($name) => print $name);
 * class_exists('App\Box<int>');   // prints nothing at all
 * class_exists('App\Generic\Box_int');   // prints the name
 * ```
 *
 * The angle brackets that make `AngleBracketNameMangler` collision-proof are exactly what puts
 * its names outside that set. Under the default mangler this autoloader is therefore **inert** -
 * correctly so, and worth knowing in its own right: a name containing `<` can never be resolved
 * behind your back, by this or by anything else.
 *
 * Pair it with `IdentifierSafeNameMangler` and it does its job:
 *
 * ```php
 * $factory = new GenericFactory(mangler: new IdentifierSafeNameMangler());
 * GenericAutoloader::register($factory);
 *
 * class_exists('App\Generic\Box_int');   // true - built on demand
 * ```
 *
 * ## What it is for
 *
 *  - **round-trips.** `unserialize()`, `var_export()` output and string-keyed DI containers all
 *    name a class and expect it to exist. A specialization exists only because somebody minted
 *    it, and after a `reset()` - or in a worker that skipped the warm-up - nobody has.
 *  - **nested types the engine resolves for itself.** Verifying a typed property whose type is a
 *    specialization goes through autoload, so this turns a `Class not found` into a transparent
 *    recovery.
 *
 * ## Why it fails quietly
 *
 * It can only work from what the name says, which is the one situation where the registry cannot
 * help - the whole premise is that this class has not been made yet. So it is at the mercy of
 * `NameMangler::parse()`, and `IdentifierSafeNameMangler` documents its parsing as best-effort:
 * `App\Generic\Box_App_User` cannot be told apart from a two-argument specialization. A name that
 * turns out not to describe a template this package can build is treated as somebody else's
 * class - the autoloader returns and `class_exists()` answers `false`, exactly as it would have
 * without it. An autoloader that threw on every unfamiliar name in a `Generic` namespace would be
 * worse than useless.
 */
final class GenericAutoloader
{
    private bool $registered = false;

    /**
     * @param GenericFactory|null $factory Resolved through `Generic::factory()` when null, so
     *                                     that a later `Generic::setFactory()` still applies
     */
    public function __construct(private readonly ?GenericFactory $factory = null) {}

    /**
     * Installs the autoloader and hands it back so it can be unregistered again
     */
    public static function register(?GenericFactory $factory = null): self
    {
        $autoloader = new self($factory);
        spl_autoload_register($autoloader, true, false);
        $autoloader->registered = true;

        return $autoloader;
    }

    public function unregister(): void
    {
        if ($this->registered) {
            spl_autoload_unregister($this);
            $this->registered = false;
        }
    }

    public function isRegistered(): bool
    {
        return $this->registered;
    }

    public function __invoke(string $className): void
    {
        $factory = $this->factory ?? Generic::factory();
        $mangler = $factory->mangler();

        // The cheap rejection first: for AngleBracketNameMangler this is one str_contains, so an
        // ordinary class name loses here and pays a strpos for the privilege
        if (!$mangler->isMangled($className)) {
            return;
        }

        $parsed = $mangler->parse($className);
        if ($parsed === null || !class_exists($parsed->templateName)) {
            return;
        }

        try {
            $factory->specialize($parsed->templateName, ...$parsed->typeArguments);
        } catch (GenericsException) {
            // Not something this package can build from that name - see the class docblock
        }
    }
}

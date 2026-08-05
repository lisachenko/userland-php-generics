<div align="center">

# 🧬 Userland PHP Generics

### Reified generics for PHP. In userland. Today.

**`Box::of('int')` does not return a wrapper, a validator or a docblock promise. It returns a
real class whose `int` is enforced by the Zend Engine itself — the same `TypeError` you get
from a hand-written class, on a class that did not exist a microsecond ago.**

[![PHP Version](https://img.shields.io/badge/php-8.4-8892BF.svg)](https://www.php.net/)
[![License](https://img.shields.io/packagist/l/lisachenko/userland-php-generics.svg)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](https://phpstan.org/)

</div>

---

> **⚠️ Experimental — not for production.** A proof of concept built on
> [`lisachenko/z-engine`](https://github.com/lisachenko/z-engine), which manipulates Zend Engine
> internals through FFI.

## Contents

- [Why this exists](#why-this-exists)
- [How it looks](#how-it-looks)
- [How it works](#how-it-works)
- [Requirements and installation](#requirements-and-installation)
- [Writing templates](#writing-templates)
- [Type arguments](#type-arguments)
- [What the engine actually enforces](#what-the-engine-actually-enforces)
- [Identity: sibling, not subclass](#identity-sibling-not-subclass)
- [Static analysis](#static-analysis)
- [Long-running processes](#long-running-processes)
- [Known limitations](#known-limitations)
- [Design notes](#design-notes)
- [Contributing](#contributing)

## Why this exists

The [PHPGenerics RFC](https://wiki.php.net/rfc/generics) and the PHP Foundation's work on
compile-time generics keep running into the same objection: **monomorphization costs too much
memory to put inside php-src.** Nobody has published real numbers, because until recently
nobody could monomorphize a PHP class at all.

This package can. It uses z-engine's `ClassSpecializer` to deep-clone a template's
`zend_class_entry` under a new name and rewrite the `zend_type` of its properties, parameters
and return types — while **sharing the compiled method bodies** through the engine's own
op_array refcount. The marginal cost of a specialization is therefore roughly
`sizeof(zend_op_array)` per method, *independent of how large those methods are*, rather than a
full copy of the opcodes.

Measuring that is half the point of this repository.

## How it looks

```php
use Lisachenko\Generics\Attribute\TemplateParameter;
use Lisachenko\Generics\{GenericObject, GenericTemplate};

/**
 * @template T
 */
#[TemplateParameter('T')]
final class Box implements GenericObject
{
    use GenericTemplate;

    private ?T $value = null;

    public function set(T $value): void
    {
        $this->value = $value;
    }

    public function get(): ?T
    {
        return $this->value;
    }
}
```

```php
$intBox = new (Box::of('int'))();

$intBox->set(42);        // fine
$intBox->set('nope');    // TypeError: Box<int>::set(): Argument #1 ($value) must be of type int

get_class($intBox);      // "Box<int>"  — a real, registered, instantiable class
```

The template itself is left exactly as it was: `Box::$value` still has the placeholder type, so
an unspecialized `Box` accepts nothing at all.

## How it works

1. `TemplateParser` reads the `#[TemplateParameter]` attributes and walks the class's **own**
   properties, parameters and return types looking for slots that carry a type parameter.
2. `TypeArgumentResolver` parses and validates the arguments — including nested generics, which
   resolve eagerly and depth-first because the outer slot stores nothing but the inner class
   name.
3. `AngleBracketNameMangler` derives the runtime name, `Box<int>`.
4. The substitution strategies contribute to one `SubstitutionPlan`.
5. `Monomorphizer` — the single class in the package that talks to z-engine — asks
   `ClassSpecializer` to deep-clone the class entry, rewrite the types and register the result
   in `EG(class_table)`.

Every validation happens **before** the engine is asked for anything, so a rejected call can
never leave a half-registered class behind.

### Naming

A specialization is called `App\Box<int>`, and the angle brackets are load-bearing rather than
decorative:

- no PHP source can declare a class whose name contains `<`, and no PSR-4 autoloader can
  resolve one, so a specialization can never collide with a real class — the one naming hazard
  z-engine's own docs call out;
- the name still reads correctly in `get_class()`, `var_dump()` and stack traces, which is
  worth a great deal when debugging a class that exists only at runtime;
- nested arguments nest naturally: `App\Box<App\Box<int>>`.

### Caching

Specializations live in the engine's class table for the rest of the request, and
`specialize()` refuses a duplicate name. The cache therefore **adopts** rather than fails: a
name that is already registered — by another factory instance, by a warm-up at worker boot, or
after a `reset()` — is recorded and returned instead of being built a second time.

## Requirements and installation

- PHP 8.4, NTS, x86-64
- `ext-ffi` with `ffi.enable=1`
- `opcache.jit=off` (the JIT rewrites the executor internals z-engine hooks into)
- [`lisachenko/z-engine`](https://github.com/lisachenko/z-engine) on the matching branch

```bash
composer require --dev lisachenko/userland-php-generics
```

```php
use Lisachenko\Generics\Generic;

Generic::bootstrap();   // optional: boots Z-Engine now instead of on first specialization
```

Z-Engine owns the environment checks and explains anything it cannot support, so there is
nothing to probe here.

## Writing templates

Every template must:

- declare its type parameters with `#[TemplateParameter]`, once per parameter, in `of()` order;
- implement `GenericObject`;
- `use GenericTemplate` if you want `of()` on it (or call `Generic::specialize()` instead, for
  classes you do not control).

`#[TemplateParameter]` is an **attribute rather than the `@template` doc tag** on purpose. With
`opcache.save_comments=0` — a perfectly normal production setting — `doc_comment` is `NULL`, so
a doc-comment-driven runtime would break in exactly the environment people deploy to. The
`@template` tag stays the source of truth for static analysis; the runtime never reads one, and
a CI job runs the whole suite with `save_comments=0` to keep it that way.

### The two forms

**Placeholder form** — the slot declares the type parameter as its native type:

```php
private ?T $value = null;

public function set(T $value): void {}
```

`T` is a class-like type name that is never defined as a real class, which is exactly what the
engine keys on. This form works for properties, parameters and return types.

**Attribute form** — the slot announces its type parameter instead:

```php
#[Of('T')]
private mixed $value = null;
```

This is the only way to re-type a property declared `mixed`, because `mixed` has no type name
to match on. On a parameter or return type the attribute is still useful — it maps one
placeholder class onto a differently-named type parameter — but the declared type there must
still be class-like. See the next section for why.

A promoted constructor property carrying `#[Of]` produces **two** slots, the parameter and the
property, because reflection reports the attribute on both.

Both forms may be mixed freely in one class.

### Bounds

```php
#[TemplateParameter('T', of: Countable::class)]
```

mirrors `@template T of Countable`, and is checked at specialization time.

## Type arguments

The accepted grammar is deliberately a subset of PHPStan's type syntax, so the same strings
mean the same thing to the runtime and to static analysis:

| Written | Meaning |
|---|---|
| `int`, `float`, `string`, `bool`, `true`, `false`, `null`, `array`, `object`, `mixed` | builtin types |
| `App\User` | any existing class, interface or enum |
| `App\Box<int>` | a nested generic, materialized innermost-first |
| `?int` | **rejected** — see below |
| `int\|string`, `Countable&Traversable` | **rejected**, no `zend_type` can hold them |
| `iterable`, `callable`, `void`, `never`, `resource`, `static`, `self`, `parent` | **rejected** |

`iterable` deserves its own mention: it is `array|Traversable`, so it has no single engine type
mask and would otherwise be silently treated as the name of a class called "iterable".

**Nullable arguments are refused rather than silently mishandled.** Substitution preserves
whatever nullability the template declared and cannot introduce it, so `Box<?int>` would
quietly produce a non-nullable slot. Declare the slot as `?T` in the template instead.

Nesting depth is capped (default 8). No finite argument can recurse forever — the brackets are
written out — so the cap is a stack guard, not a cycle guard.

## What the engine actually enforces

**Rewriting a type and having it enforced are not the same thing.** This is the single most
important thing to know about the whole approach:

| Slot | Declared as a class-like type | Declared as a builtin (`mixed`, `int`, …) |
|------|-------------------------------|--------------------------------------------|
| Property | rewritten and enforced | rewritten and enforced |
| Parameter | rewritten and enforced | **rejected** |
| Return type | rewritten and enforced | **rejected** |

A property write always consults `zend_property_info` at run time, so a property can be
re-typed whatever it was declared as. Parameters and return values are different: for a builtin
type the compiler resolves the check at compile time and selects a specialized `ZEND_RECV` /
`ZEND_VERIFY_RETURN_TYPE` handler — and those opcodes are **shared with the template**, which is
what makes monomorphization cheap in the first place. Rewriting such a declaration changes what
reflection reports and changes nothing about what the engine enforces.

Rather than hand back a class that looks specialized and silently stops checking, both this
package and z-engine reject those cases with an exception that names the reason.

This is also the reason there is no compile-time AST-rewriting mode: `zend_ast_process` does not
fire on an opcache cache hit, so under any normal production configuration the rewrite would
never happen and the specialization would silently carry no types at all.

## Identity: sibling, not subclass

> **A specialization is a _sibling_ of its template, not a subclass.** `$intBox instanceof Box`
> is `false`.

This is the copy model — the specialization shares the template's parent and interfaces rather
than extending it — and it is not fixable. What follows from it:

- **Type-hint an interface or an abstract base, never the template class.** Both are preserved
  onto every specialization.
- `GenericObject` is required precisely so that at least one relation always survives.
- `catch (Box $e)` has the same problem, for the same reason.
- `self::class` and `__CLASS__` inside a method body still name the *template*, because the
  compiler folded them into the shared opcodes. `static::class` is correct and resolves to the
  specialization.

```php
interface BoxInterface extends GenericObject {}

/** @template T @implements BoxInterface<T> */
#[TemplateParameter('T')]
final class Box implements BoxInterface { use GenericTemplate; }

function consume(BoxInterface $box): void {}   // works for every Box<X>
```

## Static analysis

The `@template` doc tags are what PHPStan and your IDE read, and they keep working normally.

The **placeholder form** needs one extra step. A native `T` is exactly what the engine wants,
but PHPStan resolves it to an object type and lets it beat any `@param T`, so every call site on
a specialization becomes `expects Fixture\T, int given`. A PHPStan **stub file** replaces the
declaration for analysis with the `mixed`-typed shape the class actually behaves like:

```neon
parameters:
    stubFiles:
        - phpstan/box-stub.php
```

Two things worth knowing about stub files, both learned the hard way:

- PHPStan indexes **only the first class declaration** in a stub file and ignores the rest, so
  write one class per file.
- Stub files are reflected before the analysed paths are indexed, so a stub cannot name your own
  interfaces or traits — declare the members it needs directly.

The **attribute form** needs no stubs for its properties, since they are natively `mixed`.

## Long-running processes

Specialization is **request-scoped**: the class entry and its tables are request memory, and the
registration lives until the request (or worker) ends. Nothing survives shutdown.

- In a worker runtime (RoadRunner, Swoole, FrankenPHP) specialize once at boot, exactly as you
  would with any other class-surgery API.
- In FPM you pay the warm-up per request; budget it.
- **Specializing during `opcache.preload` is not supported** — the preload request's allocations
  are released at its end.

## Known limitations

| Limitation | Why | What to do instead |
|---|---|---|
| **Sibling, not subclass** — `$box instanceof Box` is `false` | the copy shares the template's parent and interfaces, it does not extend it | type-hint an interface or abstract base; both are preserved |
| **`self::class` / `__CLASS__` name the template** | the compiler folded them into opcodes the copy shares | use `static::class` |
| **`array<T>` / `iterable<T>` element types are not enforced** | `zend_type` has no parametric array type; only the top-level declaration is checked | the doc tag still carries it, so PHPStan enforces it statically — the engine does not. This is the most likely source of false confidence |
| **Builtin-typed parameters and return types cannot be re-typed** | the check was compiled into shared opcodes | declare the slot with a placeholder type; properties have no such restriction |
| **Union and intersection type arguments** | no `zend_type` can hold them without building a type list | rejected loudly at resolution time |
| **Nullable type arguments** | substitution preserves the template's nullability and cannot add it | declare the slot as `?T` |
| **Request-scoped** | class entries are request memory | specialize at worker boot; not supported during preload |
| **Templates must be plain userland classes** | the specializer rejects interfaces, traits, enums, internal classes, internal ancestors, unlinked classes and property hooks | generic *interfaces* stay non-generic at runtime, which is the recommended identity pattern anyway |
| **A type parameter declared by an ancestor cannot be substituted** | inherited `property_info` / `arg_info` are shared with the declaring class | declare generic slots on the template itself |
| **Private property names keep the template's mangled prefix** | the mangled name string is shared | cosmetic only; slot access is offset-based and correct |
| **Specialized names are unparseable by PHP** | deliberate — it is what guarantees no collision | use `Box::of()`; the name is still readable everywhere it is printed |
| **PHP 8.4 NTS x64, `ffi.enable=1`, `opcache.jit=off`** | engine struct layouts are version- and build-specific | mirror z-engine's branch-per-minor model |
| **Generic methods and generic functions** | no engine primitive for method-level specialization | out of scope |

## Design notes

Three alternatives were considered and rejected; they are recorded here because the reasons are
the interesting part.

**Compile-time AST rewriting.** Rewriting `mixed` into a placeholder through
`Core::setASTProcessHandler()` would have given the nicest source syntax. But
`zend_ast_process` does not fire on an opcache **cache hit** — i.e. on every production deploy
and every second CLI run with a file cache. The template would compile from cache with `mixed`
intact, substitution would find nothing, and `specialize()` would report success while
producing a class that enforces nothing. A generics library whose type checking evaporates
under opcache is worse than none.

**Doc-comment-driven templates.** Reading `@template` at runtime breaks under
`opcache.save_comments=0`. Attributes are the runtime source of truth; a CI job enforces it.

**Making the attribute form work for `mixed` parameters.** See
[what the engine actually enforces](#what-the-engine-actually-enforces) — it cannot be made to
work without recompiling the method bodies, which is precisely the cost this whole approach
exists to avoid.

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md) and [`AGENTS.md`](AGENTS.md).

```bash
composer test          # test suite
composer phpstan       # level max
composer cs:check      # coding standards
```

## License

Released under the [MIT License](LICENSE).

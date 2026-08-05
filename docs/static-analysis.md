# Static analysis

The premise of this package is that you write ordinary PHPStan-style generic code and the
engine enforces it at run time. This document is the other half of that: what the shipped
PHPStan extension infers, what it refuses to let you write, and why a placeholder-form template
needs a generated stub at all.

## Installation

With [`phpstan/extension-installer`](https://github.com/phpstan/extension-installer) there is
nothing to do. Without it, include the extension by hand:

```neon
includes:
    - vendor/lisachenko/userland-php-generics/extension.neon
```

## What it infers

```php
$box = new (Box::of('int'))();   // Box<int>
Box::of('int');                  // class-string<Box<int>>
Generic::specialize(Box::class, 'int');  // class-string<Box<int>>
Generic::new(Box::class, ['int']);       // Box<int>
```

`new (expr)()` is ordinary PHP, and PHPStan resolves `new` on a `class-string<X>` to `X` — so
getting the class-string right is the whole job, and nothing has to be annotated at the call
site.

Type arguments are resolved through PHPStan's own type parser rather than a second
implementation, which is why the grammar was deliberately made a subset of PHPStan's. A nested
`Box<Box<int>>` therefore works with no extra code.

**Inference degrades rather than guesses.** When an argument is not a literal string, or the
number of arguments does not match the `@template` tags, the extension returns nothing and
PHPStan keeps the declared return type:

```php
Box::of($runtime);          // class-string<Box> — not narrowed, and not wrong
Box::of('int', 'string');   // class-string<Box> — arity mismatch, the runtime will say so
```

A confidently wrong inferred type makes the analyser lie, which is worse than the unhelpful one
it replaced. Those cases are asserted in the test suite on purpose.

## What the rules catch

| Rule | Catches | Because |
|---|---|---|
| `InstanceofGenericTemplateRule` | `$x instanceof Box` | A specialization is a **sibling** of its template, so this is always `false` |
| `CatchGenericTemplateRule` | `catch (Box $e)` | Same relation, worse failure: a `catch` that never matches lets the exception keep travelling |
| `TemplateParameterConsistencyRule` | `#[TemplateParameter]` and `@template` disagreeing | The runtime reads one and the analyser reads the other, and nothing in the language keeps them in step |
| `UnsupportedTypeArgumentRule` | `Box::of('iterable')`, `'?int'`, `'int\|string'` | No `zend_type` slot can hold them; the messages come from the same table the runtime throws from |
| `SlotAttributeRule` | `#[Of('T')]` naming an undeclared parameter, or marking an untyped or composite slot | The same checks `TemplateParser` makes, moved to where you are typing |
| `SelfClassInTemplateRule` | `self::class` inside a template | It names the template: bodies are shared, and the compiler folded that constant in |
| `PropertyHooksInTemplateRule` | Property hooks on a template | The specializer refuses the class outright, so it could never be specialized |

The first is the one worth installing the extension for on its own. `instanceof` against a
template is the single most surprising thing about this design, nothing about the call site
looks wrong, and without a rule the only way to discover it is to ship it.

## Generated stubs, and why they are needed

A **placeholder-form** template declares the type parameter as its *native* type:

```php
/** @template T */
#[TemplateParameter('T')]
final class Box implements GenericObject
{
    private ?T $value = null;
    public function set(T $value): void { /* ... */ }
}
```

That is exactly what the engine needs — substitution keys on the type name — and exactly the
opposite of what an analyser needs. PHPStan resolves the native `T` to an object type and lets
it beat any `@param T`, so every call site on a specialization reads
`expects App\T, int given`.

A stub file **replaces** the declaration for analysis, which makes it the only mechanism that
can describe the class the way it actually behaves. Generate them:

```bash
vendor/bin/generics-stubs --out=var/generics-stubs 'App\Box' 'App\Map'
```

and point PHPStan at the result:

```neon
parameters:
    stubFiles:
        - var/generics-stubs/box-stub.php
        - var/generics-stubs/map-stub.php
    scanFiles:
        - var/generics-stubs/placeholders.php
```

`--check` writes nothing and exits non-zero if any stub is out of date, which is what to run in
CI if you commit them.

The **attribute form** needs no stubs for its properties, since they are natively `mixed`
already. That is the concrete measure of its advantage.

### Two constraints the generator obeys

Both were learned the hard way and are not negotiable:

- **One class per stub file.** PHPStan indexes only the first class declaration in a stub and
  silently ignores the rest, so a file with three classes describes one.
- **A stub cannot name your own interfaces or traits.** Stub files are reflected before the
  analysed paths are indexed. That is why the generated `of()` is written out in full rather
  than inherited from `GenericTemplate`.

### The placeholders file

The placeholder types never exist at run time — an undefined class name is precisely what gives
the engine something to substitute — but PHPStan has to resolve them to analyse the template's
own file at all. `placeholders.php` declares them for analysis only, and belongs in `scanFiles`
rather than `stubFiles`.

## This package analyses itself with its own extension

`phpstan.dist.neon` includes `extension.neon`, and the stubs in `tests/phpstan/generated/` are
produced by `composer stubs:generate` and committed. `composer stubs:check` runs in CI and
fails on any diff.

That is deliberate rather than tidy: it means a rule that crashes, a service that cannot be
wired, or a generator change that stops producing what the project relies on all fail the build
here rather than in somebody's project. It has already earned its place — the first run
reported `SlotAttributeRule` firing on a fixture that exists precisely to be invalid.

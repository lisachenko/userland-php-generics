# Working on userland-php-generics

This package sits directly on top of [`lisachenko/z-engine`](https://github.com/lisachenko/z-engine)
and manipulates live `zend_class_entry` structures through it. **Read
[z-engine's `AGENTS.md`](https://github.com/lisachenko/z-engine/blob/master/AGENTS.md) first — every
rule in it applies here too.** The rules below are the additions that are specific to this package.

## 1. Version matching is still non-negotiable

Engine struct layouts are version-specific. This package tracks **one PHP minor at a time**, the same
one the z-engine branch it depends on tracks. Never run the suite against a different minor, and never
try to defeat `Core::init()`'s guard.

Running anything that touches the engine requires:

```bash
php -d ffi.enable=1 -d opcache.jit=off vendor/bin/phpunit
```

The JIT rewrites the executor internals z-engine hooks into, and `ffi.enable` is `PHP_INI_SYSTEM`, so
neither can be set from `phpunit.xml`.

## 2. Nothing in the runtime path may read a doc comment

`opcache.save_comments=0` is a normal production setting, and it makes
`zend_class_entry->doc_comment` `NULL`. A generics runtime that reads `@template` out of a docblock
therefore breaks in exactly the environment people deploy to.

> **Attributes are the runtime source of truth. Doc comments are the static-analysis source of
> truth.** The two are kept in sync by a PHPStan rule, never by the runtime.

The `tests-opcache` CI job runs the whole suite with `opcache.save_comments=0`. It is not optional
and it is not allowed to be skipped.

## 3. Validate before you touch the engine

`ClassSpecializer` guarantees that a failed `specialize()` call never leaves a half-built class
behind. This package must not weaken that: every template and every type argument is validated
*before* the engine call, so a rejection is a plain exception and not a partially registered class.

## 4. Engine rejections are translated, never leaked raw

A `ClassSpecializationException` explains a problem in engine terms ("classes with property hooks are
not supported"). Users of this package are writing generic templates, not manipulating class entries,
so every engine rejection is re-thrown as a `SpecializationException` phrased in template terms, with
the original attached as `$previous`. Never let a raw z-engine exception reach the caller.

## 5. The engine's `TypeError` is the product — never catch it

When a specialized class rejects a value, the engine throws `TypeError`. That is the entire point of
the library. Do not wrap it, do not translate it, do not catch it anywhere in the runtime path.
`GenericsException` is deliberately an interface so that `catch (GenericsException)` cannot
accidentally swallow it.

## 6. Test isolation: unique specialized names

Specialized classes are registered in `EG(class_table)` for the rest of the process, and
`specialize()` throws on a duplicate name. Tests therefore mint **unique target names**, via
`SpecializationIsolationTrait`, which decorates the name mangler with a per-test discriminator. Only
tests that must assert the *canonical* mangled name may use it, and each such name is used exactly
once in the suite.

Anything that deletes from the class table belongs in `#[Group('internal')]` and runs under
`composer test:internal` with process isolation, exactly as in z-engine.

## 7. Quality gates (all enforced in CI)

```bash
composer test          # engine + analysis suites
composer phpstan       # PHPStan at level max
composer cs:check      # php-cs-fixer (@PER-CS2.0); composer cs:fix to apply
```

New code must be clean at level max. Do not add to a baseline without a good reason.

## 8. Exceptions are raised through static named constructors

Same rule as z-engine: each failure mode is a `public static` factory on the exception class, never a
hand-written message at the call site. Add a factory rather than an inline
`throw new SomeException("...")`.

## 9. Conventional commits

See [conventionalcommits.org](https://www.conventionalcommits.org/). Common scopes here: `runtime`,
`template`, `type`, `naming`, `phpstan`, `bench`, `ci`, `docs`.

```
feat(runtime): reify generic templates through class specialization
fix(naming): keep nested type arguments stable across manglers
docs: document the sibling-not-subclass identity model
test(template): cover promoted properties producing two slots
```

## 10. Repository map

```
src/Attribute/    the template and slot attributes users write
src/Template/     parsing a template class into a TemplateDefinition
src/Type/         type-argument grammar, validation and resolution
src/Naming/       specialized class-name mangling and parsing
src/Runtime/      the boot guard, cache, registry and monomorphizer
src/Strategy/     the two substitution strategies (placeholder and attribute forms)
src/Exception/    the exception hierarchy
src/PHPStan/      the shipped static-analysis extension
benchmarks/       the monomorphization cost harness
docs/             long-form guides, the support matrix and the benchmark numbers
```

# Working on userland-php-generics

This package sits directly on top of [`lisachenko/z-engine`](https://github.com/lisachenko/z-engine)
and manipulates live `zend_class_entry` structures through it. **Read
[z-engine's `AGENTS.md`](https://github.com/lisachenko/z-engine/blob/master/AGENTS.md) first — every
rule in it applies here too.** The rules below are the additions that are specific to this package.

## 0. Decisions already taken — do not relitigate

These were settled with measurements, not preferences. Changing one is a design decision that
needs a reason, not a refactor. Each is explained in full in the README.

1. **Attributes are the runtime source of truth; doc comments are the static-analysis source of
   truth.** `opcache.save_comments=0` makes `doc_comment` NULL, so nothing on the runtime path
   may read one. The `tests-opcache` CI job enforces this permanently.

   *(Items here are revised when measurement says so — item 2 replaced a broader claim that
   builtin signatures could never be enforced, which turned out to be true of only two cases.)*

2. **Know where each check lives before changing a substitution path.** A property write consults
   `zend_property_info`; a return value is checked by a `ZEND_VERIFY_RETURN_TYPE` opline reading
   `arg_info`; a plain parameter is checked against a mask the compiler **cached into the
   `ZEND_RECV` opline**, so re-typing one means patching that cache and therefore un-sharing the
   method's opcode array. The two rejections that remain are the return types the compiler
   emitted no check for at all (`mixed`, or a provably valid return) — there is no opline to
   make those take effect. If you find yourself relaxing *those*, you are about to ship a class
   that silently stops checking. See docs/design.md.

3. **No compile-time AST rewriting.** `zend_ast_process` does not fire on an opcache cache hit,
   so under any normal production configuration the rewrite would never happen and the
   specialization would carry no types at all. Same failure mode as (2): silence, not an error.

4. **Angle brackets in specialized names are load-bearing.** No PHP source can declare a class
   containing `<` and no PSR-4 autoloader can resolve one, which is what makes a collision with
   a real class impossible. Do not "sanitize" the mangled name.

5. **Nullable type arguments are refused.** Substitution preserves the nullability the template
   declared and cannot introduce it, so accepting `Box<?int>` would quietly produce a
   non-nullable slot. The fix is `?T` in the template, not leniency here.

6. **A specialization is a sibling, not a subclass.** `instanceof` against the template is
   `false` and cannot be made true. `GenericObject` is required so one relation always survives.
   Tests assert this behaviour on purpose — do not "fix" them.

7. **The cache adopts, it does not fail.** A specialized name that is already registered is
   recorded and returned. Removing this breaks a second factory instance, a warm-up at worker
   boot, and every `reset()`.

8. **Validation happens before the engine is touched.** A rejected call must never leave a
   half-registered class behind. New checks go with the other checks, not after the
   `specialize()` call.

9. **One class per PHPStan stub file.** PHPStan indexes only the first class declaration in a
   stub and ignores the rest, and a stub cannot name your own interfaces or traits because stubs
   are reflected before the analysed paths are indexed.

10. **Benchmark findings are computed from the run, never written by hand.** Every sentence
    under a table in `docs/benchmarks.md` is generated from the numbers that run produced, so a
    result that moves cannot leave a stale claim behind it. If you catch yourself hard-coding a
    conclusion a scenario is supposed to establish, that is the bug — the harness already found
    one prediction wrong (see item 11), and it could only do so because it reports rather than
    asserts.

11. **A class-typed property write is ~2.3x a compiled one, and that is measured.** Its cost
    scales with the type argument's class-name length, which says the name is being resolved on
    every write. Everything else - dispatch, class-typed parameters, builtin-typed properties -
    is at parity. Do not "explain" this away in docs; if you fix it, fix it in z-engine and
    re-run `composer bench`. See docs/design.md §4.

12. **The committed PHPStan stubs are generated, not written.** `tests/phpstan/generated/` is
    the output of `composer stubs:generate`, and `composer stubs:check` fails CI on any diff.
    Editing one by hand works until the next run silently throws the edit away. The project
    analyses itself with its own `extension.neon` for the same reason: a rule that crashes or a
    generator that drifts fails here rather than in somebody's project.

13. **`Monomorphizer` is the only class that talks to z-engine.** Everything else describes what
    should happen. Keeping the dependency in one place is what makes it possible to say exactly
    when engine state is touched.

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
`specialize()` throws on a duplicate name. Every canonical specialized name must therefore be used
**exactly once across the whole suite** — two tests specializing `Box<int>` differently will collide,
and the second one is the one that fails. Give each test its own type argument, or its own fixture.

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
src/Runtime/      the cache, the engine-capability probe and the monomorphizer
src/Strategy/     the substitution strategies (placeholder and attribute forms) and their plan
src/Exception/    the exception hierarchy
src/PHPStan/      the shipped static-analysis extension
benchmarks/       the monomorphization cost harness
docs/             long-form guides, the support matrix and the benchmark numbers
```

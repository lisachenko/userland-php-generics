# Limitations

Every entry here is a thing this package **cannot** do, paired with what to do instead. That
pairing is the point: a limitation stated on its own reads as an apology, and a limitation with
its mitigation next to it is a design constraint you can work with.

They fall into three groups, and the difference between them matters more than any individual
entry:

- **Loud** — rejected at specialization time with a named exception. You find out immediately.
- **Structural** — the specialization works exactly as documented; it simply is not the thing
  people assume it is. You find out by reading, which is why these are first.
- **Quiet** — the code runs, nothing throws, and less is checked than it looks like.
  [`array<T>` element types](#arrayt-and-iterablet-element-types-are-not-enforced) is the only
  one, and it is the most important paragraph in this file.

## Contents

- [Structural: what a specialization is](#structural-what-a-specialization-is)
- [Quiet: where less is checked than it looks](#quiet-where-less-is-checked-than-it-looks)
- [Loud: rejected at specialization time](#loud-rejected-at-specialization-time)
- [Not built, and not planned for 1.0](#not-built-and-not-planned-for-10)
- [Cost, not correctness](#cost-not-correctness)
- [Environment](#environment)

---

## Structural: what a specialization is

### A specialization is a sibling, not a subclass

`$box instanceof Box` is `false` for `Box<int>`, and no amount of work makes it true.

**Why.** The specialization is a *copy* of the template's `zend_class_entry`. A copy inherits
what the original inherited — the same parent, the same interface list — but the copy is not
placed underneath the original in the hierarchy, because nothing in the template's class entry
says it should be.

**What to do instead.** Type-hint an interface or an abstract base; both are preserved onto every
specialization. `GenericObject` is required precisely so that at least one relation always
survives. Where you would reach for `instanceof`, ask the question a different way:

```php
Generic::isSpecialization($box);              // true
Generic::isSpecialization($box, Box::class);  // true - "made from this template"
Generic::templateOf($box);                    // 'App\Box'
Generic::bindingOf($box);                     // ['int']
```

The shipped PHPStan rule reports `instanceof` against a template, so this is caught in analysis
rather than in production. `catch (Box $e)` has the same problem for the same reason, and has its
own rule.

### `self::class` and `__CLASS__` name the template

Inside a method body, both still say `App\Box` even on `App\Box<int>`.

**Why.** The compiler folded them into the opcodes at compile time, and the specialization shares
those opcodes — that sharing is the memory result this package exists to demonstrate. Un-sharing
every method to rewrite two constants would cost exactly what the approach is meant to save.

**What to do instead.** `static::class` is resolved at run time and correctly names the
specialization. A PHPStan rule reports `self::class` inside a template.

### Private property names keep the template's mangled prefix

`Box<int>`'s private `$value` is still stored under the name mangled for `Box`.

**Why.** The mangled name string is shared with the template rather than rebuilt.

**What to do instead.** Nothing. Property access is offset-based and entirely correct; this is
visible only if you read the raw property table, and it is cosmetic there too.

### Specialized names cannot be written in PHP source

There is no way to type `Box<int>` as a class name, and no PSR-4 autoloader can resolve it.

**Why.** Deliberate. A name containing `<` is one no PHP source can declare, which is what makes a
collision between a specialization and a real class *impossible* rather than merely unlikely.

**What to do instead.** `Box::of('int')` returns the name; the shipped PHPStan extension narrows
it to `class-string<Box<int>>` so static analysis follows along. The name still reads correctly in
`get_class()`, `var_dump()` and stack traces. If some tool in your chain genuinely cannot cope,
`IdentifierSafeNameMangler` produces `App\Generic\Box_int` instead — see the README for what that
costs.

### A specialization with the default name cannot be autoloaded

`class_exists('App\Box<int>')` is `false` and no autoloader is consulted — not this package's,
not Composer's, not yours.

**Why.** PHP consults the autoload stack only for names that are *valid class names*: a label,
optionally with namespace separators. A name containing `<` fails that test before any autoloader
runs, so nothing in the process ever sees it. This follows directly from the entry above and is
the same property, viewed from the other side.

**What to do instead.** Mint what you need with `Generic::warmUp()` at boot, which is the right
answer anyway. If you genuinely need name-driven materialization — `unserialize()`, a
string-keyed DI container — combine `GenericAutoloader` with `IdentifierSafeNameMangler`, whose
names *are* valid identifiers. Note the second-order cost: that mangler's `parse()` is best-effort,
so a specialization on a class-typed argument cannot be recovered from its name and the autoloader
will answer `false` for it. Builtin type arguments round-trip exactly.

---

## Quiet: where less is checked than it looks

### `array<T>` and `iterable<T>` element types are not enforced

**This is the one to read twice.** A slot declared `array` and documented `array<T>` gets its
*top-level* type checked and nothing else. `Collection<int>` will accept
`['not', 'ints', 'at', 'all']` at run time without a murmur, because what the engine checks is
"is this an array", and it is.

**Why.** `zend_type` has no parametric array type. There is nothing to write into the slot that
would express "array of int", so there is nothing for the engine to check.

**What to do instead.** Understand exactly which of your two type checkers is doing the work.
The `@param array<T>` doc tag is real and PHPStan enforces it statically — that part is not
weakened. What is missing is the run-time half, which everywhere else in this package is the part
you are relying on. If a boundary needs run-time element checking (decoded JSON, a queue payload,
anything crossing a process edge), check the elements yourself; a specialization will not do it
for you and will not tell you it did not.

This is the most likely source of false confidence in the package, which is why it is stated this
bluntly.

**For scalars, there is now a way out.** A
[native data vector](native-vectors.md) puts the elements in a block of memory instead of an
array, which moves the element type from a slot the engine cannot check (`array`) to method slots
it can: `NativeVector<int>` really does reject `1.5`, with the engine's own `TypeError`. It
covers `int` and `float` today. It is not a general answer — an `array<User>` is still an
`array` — and it is a different data structure rather than a fix to this entry.

---

## Loud: rejected at specialization time

Everything in this group throws a named exception before the engine is touched, so a rejected
call never leaves a half-registered class behind.

### A return type the compiler emitted no check for cannot be re-typed

A `mixed` return, or one the compiler already proved satisfies the declared type.

**Why.** A return value is checked by a `ZEND_VERIFY_RETURN_TYPE` opline that reads `arg_info`.
Where the compiler emitted no such opline, rewriting `arg_info` changes what reflection reports
and nothing else — the class would silently stop checking.

**What to do instead.** Give the method a non-`mixed` return type and a return the compiler cannot
fold. `SpecializationException` names the method; it is never accepted quietly.

### A builtin *parameter* cannot be re-typed when the body is opcache-shared

**Why.** A plain parameter is checked against a type mask the compiler **cached into the
`ZEND_RECV` opline**, so re-typing one means patching that cache and therefore un-sharing the
method's opcode array. Copying an opcode array keeps its literals where they are, and an
`IS_CONST` operand addresses them with a 32-bit offset — so the copy is rejected when the literal
table ends up more than 2 GB away, which is what happens when the body lives in opcache shared
memory and the copy does not.

**What to do instead.** Declare that parameter with a placeholder type (`T`) rather than a builtin
plus `#[Of]`. A placeholder parameter reads `arg_info` directly and needs no un-sharing, so it is
unaffected. Tracked upstream as
[z-engine#131](https://github.com/lisachenko/z-engine/issues/131).

### Union and intersection type arguments

`Box<int|string>` and `Box<Countable&Traversable>` are refused.

**Why.** No single `zend_type` can hold them without building a type list, which is a heap
structure the substitution path does not construct.

**What to do instead.** Specialize for one of them, or declare the union in the template itself
where the compiler builds the list.

### Nullable type arguments

`Box<?int>` is refused.

**Why.** Substitution preserves whatever nullability the template declared and cannot introduce
it, so accepting `?int` would quietly produce a **non**-nullable slot. Leniency here would move
this entry into the quiet group, which is the worse outcome.

**What to do instead.** Declare the slot as `?T` in the template.

### `iterable`, `callable`, `void`, `never`, `resource`, `static`, `self`, `parent`

Refused as type arguments.

**Why.** `iterable` is `array|Traversable` and has no single type mask; the rest are not types a
declaration slot can hold at all. Accepting `iterable` would be the worst case — it would be
treated as the name of a class called "iterable".

**What to do instead.** Use `array` or `Traversable` explicitly.

### Templates must be plain userland classes

The specializer rejects interfaces, traits, enums, internal classes, classes with an internal
ancestor, unlinked classes, and classes with property hooks.

**Why.** Each of these has state the copy cannot own safely — an internal ancestor's handlers, a
trait's flattening, an enum's case table, a hook's closures.

**What to do instead.** Keep the template a plain class and put the shared identity on an
interface. Generic *interfaces* stay non-generic at run time, which is the recommended identity
pattern anyway.

### A type parameter declared by an ancestor cannot be substituted

**Why.** Inherited `property_info` and `arg_info` are shared with the declaring class. Rewriting
them would change the ancestor for every other subclass in the process.

**What to do instead.** Declare generic slots on the template itself.

---

## Not built, and not planned for 1.0

### Generic methods and generic functions

```php
function first<T>(array $items): T {}      // no
public function map<U>(callable $fn): U {} // no
```

**Why.** The engine primitive this package is built on specializes a **class entry**. There is no
equivalent for a single method or a free function: nothing to copy, nothing to key a
specialization on, and no name to register it under. This is not a gap in the implementation, it
is the absence of the thing the implementation stands on.

**What to do instead.** Put the type parameter on a class. Out of scope for 1.0, and honestly so.

### Specialization during `opcache.preload`

**Why.** The preload request's allocations are released at its end, so a class entry created there
does not survive into the requests that follow.

**What to do instead.** Preload the **templates** — that is fine and useful — and specialize at
worker boot. See [`long-running.md`](long-running.md) and the repository's `preload.php`.

### Anything surviving the request

Class entries are request memory. Nothing registered by `specialize()` outlives the request or the
worker.

**What to do instead.** Warm up at boot; budget the warm-up in FPM. Fully covered in
[`long-running.md`](long-running.md).

---

## Cost, not correctness

### A class-typed property write costs about 2.5x a compiled one

Measured, not assumed: [`benchmarks.md`](benchmarks.md) has the numbers and the probe that found
the cause.

**Why.** The cost tracks the *length of the type argument's class name*, which is what cost spent
resolving a name looks like. A compiled class resolves its property type once through a class-entry
cache attached to interned strings; the name z-engine writes into a substituted type is created at
run time and is not interned, so the fast path is missed. Suspected mechanism, not proven.

**What to do instead.** Prefer a builtin type argument where the choice exists — a builtin-typed
property write is at parity, because there is no name to resolve. Nested generics are the worst
case, since their type-argument names are long by construction. Tracked upstream as
[z-engine#130](https://github.com/lisachenko/z-engine/issues/130); if it is fixed there, re-run
`composer bench` and this entry goes away.

Everything else — method dispatch, class-typed *parameters*, builtin-typed properties — measured
at parity with a hand-written class.

### A stub-described template does not narrow `of()`

Affects static analysis only; nothing about the run time changes. A placeholder-form template
declares its type parameter as a native type, which an analyser resolves to a class that does not
exist, so the package ships a **generated stub** describing the class the way it behaves. Once
that stub is in your PHPStan configuration, `NativeVector::of('int')` stays
`class-string<NativeVector<T>>` instead of narrowing to `class-string<NativeVector<int>>`.

**Why.** A stub cannot name this library's own interfaces — stub files are reflected before the
analysed paths are indexed — so the stubbed class is not a `GenericObject`, and
`TemplateOfReturnTypeExtension` is registered against exactly that marker (it has to be: `of()`
is a *trait* method, and a trait is not in any class's ancestry). The two mechanisms are
therefore exclusive: the stub gives you element types, the marker gives you `of()` narrowing.

**What to do instead.** Spell the specialization where it enters your code and let inference do
the rest — `/** @var NativeVector<int> $vector */` once, and `$vector->get(0)` is `int`,
`iterator_to_array($vector)` is `array<int, int>` and `$vector[0]` is `int|null` from there on.
The setup and the exact `neon` snippet are in
[`native-vectors.md`](native-vectors.md#static-analysis). Attribute-form templates are unaffected:
they need no stub, so `of()` narrows for them as it always did.

---

## Environment

### PHP 8.4, NTS, x86-64, `ffi.enable=1`, `opcache.jit=off`

**Why.** Engine struct layouts are version- and build-specific, and the JIT rewrites the executor
internals z-engine hooks into.

**What to do instead.** Mirror z-engine's branch-per-minor model: this package tracks one PHP
minor at a time, the same one the z-engine branch it depends on tracks. `Core::init()` refuses to
boot against a mismatch rather than corrupting memory, and that guard is not something to defeat.

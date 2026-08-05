# Design

How this package turns a generic template into a real class, and — the part that is impossible
to guess and took measurement to establish — where the Zend Engine actually keeps a type check.

## 1. What monomorphization means here

A specialization is a **deep copy of the template's `zend_class_entry`**, registered in
`EG(class_table)` under a new name, with the `zend_type` of selected declarations rewritten.

The interesting decision is what is *not* copied. Method bodies — opcodes, literals, compiled
variables — stay **shared** with the template through the engine's own `zend_op_array` refcount,
the same mechanism trait binding uses. Each copy gets its own `zend_op_array` struct with its own
`scope`, run-time cache and static-variable table, which is what makes `static::`, `self::` and
static properties resolve correctly per specialization.

So the marginal cost of one specialization is roughly

```
sizeof(zend_class_entry) + M × sizeof(zend_op_array) + P × sizeof(zend_property_info)
```

for M own methods and P own properties — **independent of how large those methods are**. That is
the whole reason this project exists: the compile-time-generics discussions keep stalling on
monomorphization's memory cost, and a shared-body model is a materially different trade than
duplicating opcodes per instantiation.

Two things follow from body sharing, and both show up later in this document: compile-time
constants folded into a body still say the template's name (`self::class`, `__CLASS__`), and
anything the compiler decided *while compiling that body* is shared too.

**This is now measured rather than argued.** [`docs/benchmarks.md`](benchmarks.md) reports the
cost per specialization at two body sizes fifty times apart, and for a template whose slots are
all properties, parameters of a class type, or return types, the two numbers are *identical to
the byte*. The one shape that does move is a `mixed` parameter, for the reason in §3 — it is the
only slot that un-shares an opcode array. Against a codegen monomorphizer that compiles the
specialized source, the same 32-method template costs **11.6x less** at 200 statements per
method and slightly *more* at 4, which is the honest shape of the trade: sharing bodies only
pays once there is a body worth sharing.

## 2. How type enforcement actually works

**Rewriting a type and having it enforced are not the same thing.** Which one you get depends on
where the engine keeps the check for that kind of slot.

| Slot | Where the check reads its type | What it takes to re-type it |
|---|---|---|
| Property | `zend_property_info`, consulted on every write | rewrite `arg_info`-equivalent — nothing else |
| Return type | `arg_info[-1]`, read by a `ZEND_VERIFY_RETURN_TYPE` opline on every call | rewrite the type — **if** such an opline exists |
| Parameter, `ZEND_RECV_INIT` / `ZEND_RECV_VARIADIC` | `arg_info`, on every call | rewrite the type — nothing else |
| Parameter, plain `ZEND_RECV` | a **type mask cached in the opline** | rewrite the type **and** patch the cached mask |

### The cached mask

`ZEND_RECV` does not read `arg_info` back on every call. The compiler copies the parameter's type
mask into the opline, and the handler tests that copy. `opline->op2.num` is a **verbatim copy of
`arg_info.type.type_mask`**, upper flag bits included — a by-ref `string` parameter reads
`SEND_BY_REF | MAY_BE_STRING` in both places, which is what shows the two are the same value
rather than merely agreeing.

That is why rewriting `arg_info` alone appeared to do nothing for `mixed $item`: reflection
reported the new type and the engine kept testing the old cached mask. Writing the new value into
both makes `mixed → int` reject a string and accept an int.

A class-like parameter caches only `_ZEND_TYPE_NAME_BIT`, which routes the handler down the
generic path that reads `arg_info` and resolves the name lazily — which is why class-typed
(placeholder) parameters worked from the start.

### What the compiler elides

Two return-type cases cannot be fixed by patching anything, because there is no opcode to patch:

- a **`mixed` return type** — nothing to check, so no `VERIFY_RETURN_TYPE` is emitted on the real
  return path. One still appears in the implicit `return null` epilogue, which is why the
  precondition is "*every* return is guarded", not "the method contains a check somewhere";
- a **return the compiler already proved** — `return 'x';` in a `string` method needs no check.

Both are detected up front and rejected. This is deliberate and worth stating plainly: a
specialization that looks specialized and silently stops enforcing is worse than one that refuses
to exist.

### How this was established

By dumping oplines and patching fields in a live process, not by reading php-src. If you want to
confirm any of it on another PHP version, that is the method: take a method's `zend_op_array`,
print each opline's opcode and operands, change one field, and call it. The three findings above
each came from a specific observation — the by-ref parameter agreeing in two places, the `mixed`
method carrying an epilogue check, and a literal return compiling to no check at all.

## 3. Un-sharing an opcode array

When a plain `ZEND_RECV` has to be patched, its opcodes are shared with the template, so they are
copied into request memory first. Three operand encodings, and only one of them breaks:

| Operand | Encoding | Survives a move |
|---|---|---|
| jump targets | *signed* byte offset from the opline itself | **yes** — the array moves as a unit, so relative distances are unchanged |
| `live_range`, `try_catch_array` | opline indices | **yes** |
| `IS_CONST` operands | byte offset from the opline itself | **no** — rebased by the distance moved |

The asymmetry is the point: both jumps and constants are opline-relative, but a jump's target
moves with the array and a literal's does not. Literals sit immediately after the opcodes in one
compiler-arena block, and only the opcodes are copied, so every constant operand has to absorb
the new distance. Getting this wrong does not crash — it quietly reads the wrong zval.

Which is why the copy is verified under `zend.assertions=1`: every `IS_CONST` operand must
resolve to the address it resolved to before the move, and every jump must still land inside the
array. That assertion caught a real bug on its first CI run.

One limit falls out of the encoding: an `IS_CONST` operand stores a **signed 32-bit** offset, so
the relocated opcodes must stay within 2GB of the literals they still share. Request memory and
the compiler arena are neighbours, so an ordinary class is fine — but an **opcache-shared body**
lives in an mmap'd region that can be arbitrarily far away, and it is detected and rejected
rather than silently truncated. Only a `mixed` (or otherwise builtin) *parameter* un-shares
anything at all, so class-typed parameters, every return type and every property are unaffected.
Copying the literals alongside the opcodes would lift the restriction and is the obvious
follow-up; it needs the zval ownership worked out first, and is tracked as
[z-engine#131](https://github.com/lisachenko/z-engine/issues/131).

Cost is one `zend_op` block per patched method, and only methods that actually need a patch pay
it — the shared-body model is otherwise untouched. Ownership mirrors the duplicated `arg_info`
blocks: `destroy_op_array()` frees whichever pointer its holder carries once the shared refcount
reaches zero, so one sibling block is released through the engine and the other is reclaimed by
the request allocator at request end.

## 4. What a specialization costs at run time

Minting one is not free and is not meant to be hot: on the order of **a hundred microseconds
fixed plus another hundred per own method**, because every method has its `zend_op_array` struct
copied and the copying is a long sequence of individual FFI calls from userland rather than one
engine-side memcpy. Asking for an already-minted one costs **a couple of microseconds** — a name
resolution and a cache hit — which is what makes `of()` safe to write wherever a generic type
appears. Specialize at worker boot.

Exact figures live in [`docs/benchmarks.md`](benchmarks.md) rather than here, because that file
is regenerated by `composer bench` and timings move between runs and machines; a number pinned
into prose is a number that goes quietly wrong.

Using one is where the interesting result is, and it was not the expected one.

| Slot | Cost vs the same slot on a hand-written class |
|---|---|
| builtin-typed property (`#[Of]` on `mixed`, substituted to `int`) | parity |
| class-typed property (`?T` substituted to a class) | **more than 2x** |
| method dispatch, no checks | parity |
| class-typed parameter | parity |

The class-typed property write is the outlier, and it is not mysterious once measured: its cost
**scales with the length of the type's class name**, while a compiled class is flat under the
same change. Cost that tracks name length is cost spent resolving that name, so a specialization
is looking its property type up on every write where a compiled class resolved it once. The
engine reaches its fast path through a class-entry cache carried on interned strings, and the
name written into a substituted type is created at run time rather than interned — that is the
first place to look, and it is a hypothesis this harness has not proven.

Filed as [z-engine#130](https://github.com/lisachenko/z-engine/issues/130), where the diagnosis
is written up in full — including why the obvious fix is not one: `StringEntry::persistentInterned()`
sets `GC_IMMUTABLE` but explicitly does not register in the engine's interned tables, so it would
get no map-ptr slot either. `composer bench -- --scenario=dispatch` verifies any attempt, since it
reports the ratio and the name-length sensitivity together.

Two practical consequences. A builtin type argument is the cheaper one to *use*, not just the
one the attribute form exists for. And a **nested** generic pays this on every write to its inner
slot, because there the type argument is itself a specialization and its angle-bracket name is
long by construction.

## 5. The pipeline

```
                    #[TemplateParameter] class
                                │
                                ▼
                        TemplateParser
                                │
                                ▼
              TemplateDefinition (parameters + slots)
              slots are own properties/parameters/returns that carry a
              parameter, in either the placeholder or the attribute form
                       │                     │
            ┌──────────┘                     └──────────┐
            ▼                                           ▼
   TypeArgumentResolver                          StubGenerator
   concrete type names: grammar,                 renders the same slots as
   existence, bounds, nested                     `mixed` + doc tags, for an
   generics (innermost first)                    analyser rather than the engine
            │                                           │
            ▼                                           ▼
   AngleBracketNameMangler                       one stub file per template
   "App\Box<int>"                                + placeholders.php
            │
            ▼
   SubstitutionPlan
   TypeSubstitutionMap (by type name)
   + SlotSubstitutionMap (by slot); strategies
   contribute rather than compete, so one
   template may mix forms
            │
            ▼
   Monomorphizer ──► ClassSpecializer::specialize()
                     ← the only call that touches the engine
```

**One definition, two consumers.** The runtime branch reifies the template; the analysis branch
describes it. Sharing `TemplateParser` between them is what makes the generator accept exactly
what the runtime accepts, and refuse the same things for the same reasons — a template the
engine would reject cannot silently produce a stub that says otherwise.
[`docs/static-analysis.md`](static-analysis.md) covers the analysis branch in full.

**Every validation runs before the engine is asked for anything.** A rejected call cannot leave a
half-registered class behind, which is the same contract `ClassSpecializer` itself keeps.

`SpecializationCache` sits in front of this and **adopts** rather than fails: a name already in
the class table is recorded and returned, because specializations live for the rest of the
request and `specialize()` refuses a duplicate name.

## 6. Naming

`App\Box<int>`. No PHP source can declare a class whose name contains `<` and no PSR-4 autoloader
can resolve one, so a specialization can never collide with a real class — while the name stays
readable in `get_class()`, `var_dump()` and stack traces. Nested arguments nest naturally.

## 7. Identity

A specialization is a **sibling** of its template, not a subclass: it shares the template's parent
and interfaces rather than extending it, so `$box instanceof Box` is `false`. This is the copy
model and cannot be changed. `GenericObject` is a required marker precisely so that one relation
always survives; the documented pattern is to type-hint an interface or an abstract base.

Because `instanceof` cannot answer the question, the name has to. Every mangler is required to
run backwards — `parse(mangle($t, $a))` must give back `$t` and `$a` — and
`Generic::isSpecialization()`, `templateOf()` and `bindingOf()` are built on that, which is also
why they work for an instance minted by a different factory. The shipped
`InstanceofGenericTemplateRule` reports the `instanceof` and points at them, turning the most
surprising thing in this design into a static error rather than a discovery.

## 8. Alternatives considered and rejected

**Compile-time AST rewriting.** Rewriting `mixed` into a placeholder through
`zend_ast_process` would have given the nicest source syntax. But that hook does not fire on an
opcache **cache hit** — i.e. on every production deploy and every second CLI run with a file
cache. The template would compile from cache unchanged, substitution would find nothing, and
`specialize()` would report success while producing a class that enforces nothing.

**Doc-comment-driven templates.** `opcache.save_comments=0` is a normal production setting and
makes `doc_comment` NULL. Attributes are the runtime source of truth; a CI job runs the whole
suite with comments stripped to keep it that way.

**Recompiling the method.** Generating the method source with concrete types and swapping the body
in would handle even the elided-check cases, correctly and by construction — but it costs a real
compile per specialization and loses body sharing for that method, which works directly against
the memory result this project exists to measure.

**Inserting the missing `VERIFY_RETURN_TYPE` oplines.** Growing the opcode array means renumbering
every jump, `live_range` and `try_catch_array` entry. High risk for the two cases the rejection
already handles honestly.

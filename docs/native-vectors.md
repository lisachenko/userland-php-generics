# Native data vectors

`Lisachenko\Generics\Native\NativeVector` is a fixed-layout block of native scalars that you
index like an array, whose storage is an ordinary PHP string.

```php
use Lisachenko\Generics\Native\NativeVector;

$samples = new (NativeVector::of('int'))($blobFromTheWire);

echo $samples[0];        // a zend_long read straight out of the block
$samples[1] = -20;       // written straight back into it
$samples->append(50);    // the block grows by eight bytes
$bytes = $samples->toBinary();
```

It exists because of the entry in [`limitations.md`](limitations.md) that says the most:
[`array<T>` element types are not enforced](limitations.md#arrayt-and-iterablet-element-types-are-not-enforced).
`zend_type` has no parametric array type, so a slot declared `array` and documented `array<T>`
is checked for being an array and nothing else. A native vector answers that for the scalar
case by not being an array at all: the element type lives in the *method* slots, which the
engine does check, and the storage is a block of memory whose layout the element type fixes.

---

## Contents

- [The memory model](#the-memory-model)
- [Casting and the round trip](#casting-and-the-round-trip)
- [Growth](#growth)
- [What the engine enforces](#what-the-engine-enforces)
- [Copy-on-write, and why `toBinary()` is safe](#copy-on-write-and-why-tobinary-is-safe)
- [`destroy()`](#destroy)
- [Static analysis](#static-analysis)
- [What it is not, yet](#what-it-is-not-yet)

---

## The memory model

**The block of memory *is* a PHP string.** Element `i` lives at byte `i * 8` of that string's
`zend_string.val`, in the machine's own layout, and every accessor reaches it as a native
pointer:

| Type argument | C type | Width | How an element is reached |
|---|---|---|---|
| `int` | `zend_long` | 8 bytes | `Core::cast('zend_long *', $zstr->val)`, then `$ptr[$i]` |
| `float` | `double` | 8 bytes | `Core::cast('double *', $zstr->val)`, then `$ptr[$i]` |

Three facts make that a definition rather than a trick:

- **PHP scalars are fixed-width.** `int` is a `zend_long` and `float` is a `double` on every
  platform PHP supports, both 8 bytes. There is no narrower scalar to store, which is why the
  element size is a constant (`NativeVector::ELEMENT_SIZE`) rather than a per-instance field.
- **`zend_string.val` starts 8-aligned,** so `i * 8` is always a naturally aligned offset. No
  padding arithmetic, no unaligned loads.
- **There is no encoding step.** `pack()` and `unpack()` appear nowhere in the production path.
  A read initializes a PHP value from the bytes that are already there; a write copies a PHP
  value into them. The byte order, the sign representation and the float format are the
  machine's, by definition rather than by choice — which is exactly what you want when the bytes
  came from `mmap`, from a socket, or from another process on the same host, and exactly what
  you must convert for yourself when they came from a different architecture.

`pack()` appears throughout the *tests* for the opposite reason: it is an independent encoder,
and checking the block against one is how a byte-level assertion is made worth having.

## Casting and the round trip

The constructor is the cast. It adopts a binary string as the block, rejecting one that is not a
whole number of elements:

```php
$vector = NativeVector::of('int')::fromString(pack('q*', 1, 2, 3)); // or new (…)($blob)
$vector->toBinary() === pack('q*', 1, 2, 3);                        // true, byte for byte
```

`fromString()` and `new (…)($binary)` are the same thing; `withCapacity($count)` is the
zero-filled variant. `toBinary()` hands the block back as an ordinary PHP string and costs one
reference rather than a copy.

The string a vector was cast from is never written into. It may be interned (every string
literal is), or it may still be held by the caller — either way the constructor makes the block
exclusively the vector's before anything can point into it.

## Growth

Growth is literal string concatenation:

```php
$vector->append(60);                       // $this->buffer .= 8 zero bytes, then store
$vector->appendFromString(pack('q*', 7, 8)); // a whole blob at once
```

That is cheaper than it looks. The buffer is held by the vector's own property and nothing else,
so the engine extends it in place instead of copying — which is precisely why nothing in the
class holds a second reference on it. The reallocation may move the block, so the cached pointer
is dropped across every growth and re-acquired on the next access.

## What the engine enforces

`get()`, `set()` and `append()` are declared with the type parameter itself, so a specialization
carries `int` or `float` in those slots and the rejection comes from the Zend Engine:

```php
$ints = new (NativeVector::of('int'))();
$ints->append(1.5);   // TypeError: …NativeVector<int>::append(): Argument #1 ($item) must be of type int, float given
$ints[0] = 'nope';    // TypeError, on set(): the array syntax delegates
```

That delegation is the reason the class has both an element API and array syntax.
`ArrayAccess::offsetSet(mixed, mixed)` cannot narrow its parameters — PHP's contravariance rules
forbid re-declaring them — so `offsetSet()` calls `set()`, and `$vector[$i] = $x` gets exactly
the check `$vector->set($i, $x)` gets. `$vector[] = $x` appends the same way. A non-integer
offset is rejected by the same engine, for the same reason: `set()` declares `int $index` and
this package is `strict_types=1`.

Two failures are the vector's own rather than the engine's, and both are named exceptions:

- `NativeVectorBoundsException` — an index below zero or at/after `count()`. Checked before every
  read and every write, because it is what stands between a userland off-by-one and a wild
  pointer dereference.
- `NativeVectorException` — the raw template constructed with no type argument, a type argument
  with no native layout (`NativeVector<string>` is a legal class with an impossible layout), a
  misaligned binary, a negative capacity, use after `destroy()`, and `unset($vector[$i])`, which
  a contiguous block has no meaning for.

`int`-to-`float` widening is allowed by the language even under `strict_types`, so a
`NativeVector<float>` accepts `3` and stores the double `3.0`. That is not a hole in the
enforcement; it is the same rule every `float` parameter in PHP follows.

## Copy-on-write, and why `toBinary()` is safe

Writing through a pointer into a PHP string's bytes is only correct while nobody else holds that
string. `toBinary()` hands the block out, which means somebody does:

```php
$snapshot = $vector->toBinary();
$vector->set(0, 999);

$snapshot;            // unchanged - the write separated the block first
$vector->toBinary();  // the new bytes
```

The discipline behind that is deliberately small:

1. The vector caches the `zend_string *` and the typed element pointer, and holds **no engine
   reference** on the string. The property is what keeps it alive; a wrapper holding a second
   reference would push the refcount to 2 for good, defeat the in-place growth path, and make
   step 2 useless.
2. Before every write, the refcount on the cached `zend_string` is read — one field access, no
   allocation and no engine call. `1` means the block is the vector's alone and the write goes
   straight through.
3. Anything else means the block is shared, and it is separated first, by assigning a string
   offset onto itself (`$buffer[0] = $buffer[0]`). That is the userland spelling of the engine's
   own separation: `zend_assign_to_string_offset()` copies a string that is shared or immutable
   and merely forgets its cached hash when it is not, so the operation is a no-op on a block
   that is already exclusive.
4. The pointer cache is dropped whenever the block may have moved — a separation, a growth, a
   `destroy()` — and re-acquired lazily.

Reads never separate. A reader through a shared block sees the bytes everybody else sees, which
is what sharing means.

An interned string has no meaningful refcount at all — a permanent one reuses the field as the
engine's class-entry cache slot — so a block that arrives interned (a literal, or the empty
string) is separated when the pointer is acquired rather than trusted. That is why `''` is the
one buffer value the separation trick is skipped for: it has no offset zero, and no elements to
protect.

## `destroy()`

```php
$vector->destroy();   // idempotent
$vector->destroy();
$vector->get(0);      // NativeVectorException: …released by destroy()
```

There is nothing to free at the FFI level: the bytes belong to the Zend memory manager, which
reclaims them when the last reference goes. What `destroy()` does is drop the pointers and the
buffer and mark the instance unusable, so a stale index cannot be turned into a dereference of
memory that has been handed back. `count()` and `sizeInBytes()` answer `0` afterwards; every
element accessor throws.

## Static analysis

`NativeVector` is a **placeholder-form** template: it declares `get(int $index): T` natively,
because that native `T` is what the engine keys substitution on. For an analyser that
declaration is a fiction, so the package ships a generated stub —
`tests/phpstan/generated/nativevector-stub.php`, produced by `composer stubs:generate` — which
describes the class the way it behaves: `mixed` where the fiction was, with `@param T`/`@return
T` carrying the meaning, plus `@implements ArrayAccess<int, T>` and
`@implements IteratorAggregate<int, T>`.

To get element types in your own project, point PHPStan at that stub **and** keep it from
reading the real declaration, which would otherwise win:

```neon
parameters:
    excludePaths:
        analyseAndScan:
            - vendor/lisachenko/userland-php-generics/src/Native/NativeVector.php
    stubFiles:
        - vendor/lisachenko/userland-php-generics/tests/phpstan/generated/nativevector-stub.php
```

With that in place, a spelled specialization infers all the way through:

```php
/** @var NativeVector<int> $vector */
$vector->get(0);              // int
iterator_to_array($vector);   // array<int, int>
$vector[0];                   // int|null - the nullability PHPStan gives every ArrayAccess read
$vector->toBinary();          // string
```

The specialization does have to be *spelled*. A stub cannot name this library's own interfaces
(stub files are reflected before the analysed paths are indexed), so the stubbed class is not a
`GenericObject`, and the return-type extension that narrows `Template::of('int')` is registered
against exactly that marker. `NativeVector::of('int')` therefore stays
`class-string<NativeVector<T>>` once the stub is in play — write the type at the boundary, in the
`@var` or the `@param` where the vector enters your code, and everything downstream follows. This
is a documented trade-off rather than a defect on either side: with the stub you get element
types and no `of()` narrowing, without it you get `of()` narrowing and `get()` typed as the
placeholder class. `get()` is the accessor to prefer either way — it is the precisely typed one.

## What it is not, yet

- **Only `int` and `float`.** Every other type argument builds a perfectly real class whose
  constructor then refuses, because there is no layout to give it. The next phase is **sized
  scalar kinds** — `int32`, `uint16`, `float32` and friends — which turns `ELEMENT_SIZE` from a
  constant into a property and the two-way element-kind flag into a descriptor.
- **No C structures.** The phase after that is a layout description for records, so a vector of
  structs can be indexed the same way.
- **One dimension, and no slicing.** `appendFromString()` and `toBinary()` are the two bulk
  operations; anything else is done on the binary string, which is the point of it being one.
- **Request-scoped, like every specialization.** See
  [`long-running.md`](long-running.md): mint `NativeVector<int>` at worker boot, never during
  `opcache.preload`.

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

namespace Lisachenko\Generics\Native;

use ArrayAccess;
use Countable;
use FFI\CData;
use IteratorAggregate;
use Lisachenko\Generics\Attribute\TemplateParameter;
use Lisachenko\Generics\Exception\NativeVectorBoundsException;
use Lisachenko\Generics\Exception\NativeVectorException;
use Lisachenko\Generics\Generic;
use Lisachenko\Generics\GenericObject;
use Lisachenko\Generics\GenericTemplate;
use Traversable;
use ZEngine\Core;
use ZEngine\Type\StringEntry;

/**
 * A fixed-layout block of native scalars, addressed as `$vector[$i]`, stored in a PHP string
 *
 * ```php
 * $samples = new (NativeVector::of('float'))($blobFromTheWire);
 * $samples[0] = 1.5;          // a double written straight into the block
 * $samples->append(2.25);     // the block grows by one element
 * $bytes = $samples->toBinary();
 * ```
 *
 * **The memory model.** The block of memory *is* a PHP string. Element `i` lives at byte
 * `i * 8` of that string's `zend_string.val`, in the machine's own layout, and the accessors
 * dereference it as `zend_long*` or `double*` - there is no encoding step anywhere, so
 * `pack()`/`unpack()` appear nowhere in this file. Both native scalar kinds are 8 bytes wide on
 * every platform PHP supports and `zend_string.val` starts 8-aligned, so `i * 8` is always a
 * naturally aligned offset. A binary string from anywhere - a file, a socket, `pack()` in the
 * caller's own code - is *cast* to a vector by handing it to the constructor, and `toBinary()`
 * hands it back.
 *
 * **What the engine checks.** `get()`, `set()` and `append()` are declared with the type
 * parameter itself, so the specialization carries `int`/`float` in those slots and it is the
 * Zend Engine, not this class, that rejects a wrong element with a TypeError. That is the whole
 * reason the element API is not `mixed`: `ArrayAccess::offsetSet(mixed, mixed)` cannot narrow
 * its parameters, so the array-syntax sugar delegates to `set()`/`append()` and inherits their
 * checking rather than replacing it.
 *
 * **Why a template rather than a class per type.** Monomorphization shares the compiled method
 * bodies between specializations, so `NativeVector<int>` and `NativeVector<float>` cost one
 * class entry each and no code at all. The element kind is therefore resolved once, in the
 * constructor, from the specialization's own binding - never from `self::class`, which is
 * folded into the shared opcodes.
 *
 * See docs/native-vectors.md for the memory model, the copy-on-write discipline and the
 * roadmap towards sized scalar kinds and C structures.
 *
 * @template T
 *
 * @implements ArrayAccess<int, T>
 * @implements IteratorAggregate<int, T>
 */
#[TemplateParameter('T')]
final class NativeVector implements ArrayAccess, Countable, IteratorAggregate, GenericObject
{
    use GenericTemplate;

    /**
     * Both supported element kinds are exactly this wide: `zend_long` and `double`
     *
     * PHP has no narrower scalar to store, so this is a constant rather than a per-instance
     * size. Sized kinds are the next phase, and that is the field this becomes.
     */
    public const ELEMENT_SIZE = 8;

    /**
     * One element's worth of zero bytes, the unit of growth
     *
     * A literal rather than `str_repeat()`: it is interned, so appending to an empty vector
     * costs no allocation at all. That the result may then *be* the interned constant is
     * handled where it matters - see acquire().
     */
    private const ZERO_ELEMENT = "\0\0\0\0\0\0\0\0";

    /**
     * The block of memory, which is a PHP string and nothing more
     */
    private string $buffer;

    /**
     * Element kind, resolved once from the binding; there are only two, so a bool carries it
     */
    private bool $isFloat;

    private bool $destroyed = false;

    /**
     * Cached `zend_string *` for $buffer, or null when the cache needs re-acquiring
     *
     * Held without a reference of its own: the property is what keeps the string alive, and a
     * wrapper holding a second reference would push the refcount to 2 permanently, which would
     * both defeat the in-place `.=` growth path and make the exclusivity test below useless.
     */
    private ?CData $string = null;

    /**
     * Cached typed pointer at the first element - `zend_long *` or `double *`
     *
     * This is the hot path: an element access is a bounds check and one dereference through
     * this pointer. It is dropped whenever the string behind it may have moved (growth,
     * separation, destroy) and lazily re-acquired.
     */
    private ?CData $elements = null;

    /**
     * Casts an existing binary block - the vector adopts its bytes, it does not decode them
     *
     * @param string $binary A whole number of native elements; anything else has no reading
     */
    public function __construct(string $binary = '')
    {
        $binding = Generic::bindingOf(static::class);
        if ($binding === null) {
            throw NativeVectorException::notSpecialized(static::class);
        }

        [$typeArgument] = $binding;
        if ($typeArgument !== 'int' && $typeArgument !== 'float') {
            // A bound cannot express "int|float", so this is the one check the specialization
            // itself cannot make: the template is legal for any type argument, the *layout*
            // is not
            throw NativeVectorException::unsupportedElementType(static::class, $typeArgument);
        }
        $this->isFloat = $typeArgument === 'float';

        if (strlen($binary) % self::ELEMENT_SIZE !== 0) {
            throw NativeVectorException::misalignedBinary(static::class, strlen($binary));
        }

        $this->buffer = $binary;
        // The caller's string may be interned, or shared with a variable they still hold.
        // Writing into either would be a value-semantics violation, so the block is made
        // exclusively ours before anything can point into it
        $this->separate();
    }

    /**
     * The cast, spelled as a named constructor
     */
    public static function fromString(string $binary): static
    {
        return new static($binary);
    }

    /**
     * A zero-filled block of $count elements
     */
    public static function withCapacity(int $count): static
    {
        if ($count < 0) {
            throw NativeVectorException::negativeCapacity(static::class, $count);
        }

        // Zero-filling is not an encoding step: every byte written is a literal zero
        return new static(str_repeat("\0", $count * self::ELEMENT_SIZE));
    }

    public function count(): int
    {
        return intdiv(strlen($this->buffer), self::ELEMENT_SIZE);
    }

    public function sizeInBytes(): int
    {
        return strlen($this->buffer);
    }

    /**
     * Reads element $index straight out of memory
     *
     * The return type is the type parameter, so the specialization returns a declared `int` or
     * `float` and the engine verifies what came out of the block.
     */
    public function get(int $index): T
    {
        $this->assertUsable();
        $this->assertInBounds($index);

        return $this->elements()[$index];
    }

    /**
     * Stores $value into element $index, in place
     *
     * The parameter is the type parameter, so a wrong element never reaches this body: the
     * engine rejects it at the call boundary with a TypeError, which is deliberately not
     * caught anywhere in this package.
     */
    public function set(int $index, T $value): void
    {
        $this->assertUsable();
        $this->assertInBounds($index);

        $this->exclusiveElements()[$index] = $value;
    }

    /**
     * Grows the block by one element and stores $item in it
     *
     * Growth is literal string concatenation, which is what makes it cheap: the buffer is held
     * by nothing but this property, so the engine reallocates it in place instead of copying.
     * The reallocation may move it, which is why the pointer cache is dropped first.
     */
    public function append(T $item): void
    {
        $this->assertUsable();

        $this->buffer .= self::ZERO_ELEMENT;
        $this->invalidate();

        $this->exclusiveElements()[$this->count() - 1] = $item;
    }

    /**
     * Grows the block by a whole binary blob at once
     */
    public function appendFromString(string $binary): void
    {
        $this->assertUsable();

        if (strlen($binary) % self::ELEMENT_SIZE !== 0) {
            throw NativeVectorException::misalignedBinary(static::class, strlen($binary));
        }

        $this->buffer .= $binary;
        $this->invalidate();
    }

    /**
     * Hands the block back as an ordinary PHP string
     *
     * The buffer itself is returned rather than a copy, so this costs one reference and no
     * bytes. What keeps that honest is the exclusivity test on the write path: the returned
     * string now shares the block, the next write notices and separates first, and the value
     * the caller is holding is never changed behind their back.
     */
    public function toBinary(): string
    {
        $this->assertUsable();

        return $this->buffer;
    }

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        $this->assertUsable();

        // Reading through get() rather than yielding the bytes keeps one bounds check and one
        // dereference as the only way an element is ever read
        for ($index = 0, $count = $this->count(); $index < $count; ++$index) {
            yield $index => $this->get($index);
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return !$this->destroyed && is_int($offset) && $offset >= 0 && $offset < $this->count();
    }

    /**
     * @return T
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * `$vector[] = $x` appends, `$vector[$i] = $x` overwrites
     *
     * Both delegate rather than write, because these two parameters cannot be narrowed:
     * ArrayAccess declares them `mixed` and PHP's contravariance rules forbid re-declaring
     * them. Delegation is what puts the engine's checks back in front of the store - both of
     * them, because this file is `strict_types=1` and so an offset that is not an `int` is
     * rejected on the way into `set()` by the same engine that rejects a wrong element.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->append($value);

            return;
        }

        $this->set($offset, $value);
    }

    public function offsetUnset(mixed $offset): never
    {
        throw NativeVectorException::fixedLayout(static::class);
    }

    /**
     * Releases the block; idempotent, and final for this instance
     *
     * There is nothing to free at the FFI level - the bytes belong to the Zend memory manager,
     * which reclaims them the moment the last reference goes - so this drops the pointers and
     * the buffer, in that order, and marks the instance unusable so a stale index cannot be
     * turned into a dereference of memory that has been handed back.
     */
    public function destroy(): void
    {
        $this->invalidate();
        $this->buffer    = '';
        $this->destroyed = true;
    }

    /**
     * The read pointer: valid whether or not the block is shared
     *
     * Sharing is only a problem for writes. A reader through a shared block sees exactly the
     * bytes everyone else sees, which is what sharing means.
     */
    private function elements(): CData
    {
        return $this->elements ?? $this->acquire();
    }

    /**
     * The write pointer: the block is guaranteed to be ours alone before it is returned
     *
     * The test is a single field read on the cached `zend_string` - no allocation, no engine
     * call - and it is what makes `toBinary()` safe to hand the buffer out: a refcount above
     * one means somebody else is holding these bytes, and the block is separated before a byte
     * of it is written.
     */
    private function exclusiveElements(): CData
    {
        $elements = $this->elements ?? $this->acquire();
        if ($this->string !== null && $this->string->gc->refcount === 1) {
            return $elements;
        }

        $this->separate();
        $this->invalidate();

        return $this->acquire();
    }

    /**
     * Points the cache at the current buffer, making it exclusive-capable on the way
     *
     * An interned string has no meaningful refcount (a permanent one reuses the field as a
     * class-entry cache slot), so a block that arrived interned - a literal, or `''` - is
     * separated here rather than trusted. Everything downstream may then read
     * `gc->refcount` and believe it.
     *
     * The StringEntry wrapper is released immediately: its only job is to hand over the
     * `zend_string *`, and the property is what keeps that pointer alive afterwards.
     */
    private function acquire(): CData
    {
        $entry = new StringEntry($this->buffer);
        if ($entry->isInterned()) {
            $entry->release();
            $this->separate();
            $entry = new StringEntry($this->buffer);
        }

        $this->string = $entry->getRawValue();
        // Core::cast() restores the array-to-pointer decay PHP 8.3 took away, so this reads
        // "the bytes of val, as native elements" and not "the first eight bytes, as a pointer"
        $this->elements = Core::cast($this->isFloat ? 'double *' : 'zend_long *', $this->string->val);
        $entry->release();

        return $this->elements;
    }

    /**
     * Forces the engine to give this instance a block nobody else holds
     *
     * Assigning a string offset onto itself is the userland spelling of the engine's own
     * string separation: `zend_assign_to_string_offset()` copies the string whenever it is
     * shared or immutable and merely forgets the cached hash when it is not, so this is a
     * no-op on a block that is already ours and a copy on one that is not.
     *
     * The guard is not defensive: `''` is an interned empty string with no offset zero to
     * assign to, and an empty block has no elements to protect anyway.
     */
    private function separate(): void
    {
        if ($this->buffer !== '') {
            $this->buffer[0] = $this->buffer[0];
        }
    }

    /**
     * Drops the pointer cache after anything that may have moved the block
     */
    private function invalidate(): void
    {
        $this->string   = null;
        $this->elements = null;
    }

    /**
     * @throws NativeVectorException
     */
    private function assertUsable(): void
    {
        if ($this->destroyed) {
            throw NativeVectorException::destroyed(static::class);
        }
    }

    /**
     * @throws NativeVectorBoundsException
     */
    private function assertInBounds(int $index): void
    {
        $count = $this->count();
        if ($index < 0 || $index >= $count) {
            throw NativeVectorBoundsException::index(static::class, $index, $count);
        }
    }
}

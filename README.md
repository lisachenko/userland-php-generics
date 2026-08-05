<div align="center">

# 🧬 Userland PHP Generics

### Reified generics for PHP. In userland. Today.

**`Collection::of('int')` does not return a wrapper, a validator or a docblock promise. It returns a
real class whose `int` is enforced by the Zend Engine itself — the same `TypeError` you get from a
hand-written class, on a class that did not exist a microsecond ago.**

[![PHP Version](https://img.shields.io/badge/php-8.4-8892BF.svg)](https://www.php.net/)
[![License](https://img.shields.io/packagist/l/lisachenko/userland-php-generics.svg)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](https://phpstan.org/)

</div>

---

> **⚠️ Experimental — not for production.** This is a proof of concept built on
> [`lisachenko/z-engine`](https://github.com/lisachenko/z-engine), which manipulates Zend Engine
> internals through FFI.

## Why this exists

The [PHPGenerics RFC](https://wiki.php.net/rfc/generics) and the PHP Foundation's work on
compile-time generics keep running into the same objection: **monomorphization costs too much
memory to put inside php-src.** Nobody has published real numbers, because until now nobody could
monomorphize a PHP class.

This package can. It uses z-engine's `ClassSpecializer` to deep-clone a template's
`zend_class_entry` under a new name and rewrite the `zend_type` of its properties, parameters and
return types — while **sharing the compiled method bodies** through the engine's own op_array
refcount. The marginal cost of a specialization is therefore roughly `sizeof(zend_op_array)` per
method, *independent of how large those methods are*, rather than a full copy of the opcodes.

Measuring that is half the point of this repository. See [`docs/benchmarks.md`](docs/benchmarks.md).

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
}
```

```php
$intBox = new (Box::of('int'))();

$intBox->set(42);        // fine
$intBox->set('nope');    // TypeError: Box<int>::set(): Argument #1 ($value) must be of type int

get_class($intBox);      // "Box<int>"  — a real, registered, instantiable class
```

The angle brackets are deliberate: no PHP source can declare such a name and no PSR-4 autoloader can
resolve one, so a specialization can never collide with a real class — while still reading correctly
in `var_dump()` output and stack traces.

## Requirements

- PHP 8.4, NTS, x86-64
- `ext-ffi` with `ffi.enable=1`
- `opcache.jit=off`
- [`lisachenko/z-engine`](https://github.com/lisachenko/z-engine) on the matching branch

## Installation

```bash
composer require --dev lisachenko/userland-php-generics
```

```php
use Lisachenko\Generics\Generic;

Generic::bootstrap();   // boots Z-Engine once, with an actionable error if the host cannot support it
```

## Known limitations

This is a proof of concept and the limits are real. The most important one:

> **A specialization is a _sibling_ of its template, not a subclass.** `$intBox instanceof Box` is
> `false`. Type-hint an interface or an abstract base — both are preserved onto every specialization.

The full list, each paired with what to do instead, lives in
[`docs/limitations.md`](docs/limitations.md).

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md) and [`AGENTS.md`](AGENTS.md). In short:

```bash
composer test          # engine + analysis suites
composer phpstan       # level max
composer cs:check      # coding standards
```

## License

Released under the [MIT License](LICENSE).

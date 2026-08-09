# Long-running processes

Specialization is **request-scoped**. A specialized `zend_class_entry` and its property and
argument tables are allocated from request memory and registered in `EG(class_table)`; when the
request ends, all of it goes. Nothing this package builds survives a shutdown, and nothing it
builds needs cleaning up either.

That single fact decides everything below. There are exactly two questions worth asking about a
deployment: **how often do you pay to mint?** and **is there anything you must not do?**

Every number quoted here is read off [`benchmarks.md`](benchmarks.md), which `composer bench`
generates from an actual run. If a number here and a number there disagree, that file is right —
it computed its findings, this document is only citing them.

## The budget

From the current committed run:

| What | Cost |
|---|---|
| Minting one specialization | ~130-185 us fixed, plus ~100-120 us per **own method** |
| Asking for one that already exists | ~1.8 us |
| 1000 live specializations of a 4-method template | 5.9 MiB total, ~6.1 KiB each |
| Class-table lookup with 1000 of them registered | unchanged (66.4 ns before, 58.2 ns after) |

Two of those lines carry the whole argument.

**Minting is not cheap and re-asking is.** The gap is roughly two orders of magnitude, which is
why `Box::of('int')` is safe to write wherever a generic type appears — the first call in the
process pays, every later one is a name resolution and a cache hit — and why the first call
should happen somewhere you chose.

**The class table does not degrade.** It is a hash. "This will slow down class lookup" is the
objection this approach would otherwise have to answer with an assurance, so it is measured
instead. A thousand live specializations do not move it.

A note on where the minting time goes: it is a long sequence of individual FFI calls from
userland rather than one engine-side copy. That is a property of driving the engine through FFI,
not of monomorphization — a php-src implementation would not pay it.

## Worker runtimes (RoadRunner, Swoole, FrankenPHP, Octane)

The comfortable case. The worker process outlives the request, so specialize once at boot and
every request afterwards gets a cache hit.

```php
// worker bootstrap, before the request loop
Generic::bootstrap();

Generic::warmUp([
    [Box::class, ['int']],
    [Box::class, [User::class]],
    [Collection::class, [Order::class]],
]);

while ($request = $worker->waitRequest()) {
    // Box::of('int') here costs ~1.8 us
}
```

`Generic::bootstrap()` is optional but worth calling explicitly. Z-Engine boots itself from its
Composer bootstrap, so this is normally a no-op — but that boot is deliberately silent on a host
that cannot run the engine, and this is what turns the silence into a startup error rather than a
failure on whichever request happens to specialize first.

Two rules for the loop itself:

- **Do not `reset()` per request.** It clears the memoization, not the classes — the classes are
  engine state and stay registered. The next lookup adopts them, so nothing breaks, but you have
  thrown away the cache for no gain. `reset()` is for tests and for swapping factory
  configuration.
- **Specializing on a type argument that comes from request input is a leak.** Each distinct
  argument mints a class that lives for the rest of the worker's life and cannot be unregistered.
  Warm up a known set; do not specialize on user input.

## PHP-FPM

Every request is a fresh process view: nothing you specialized last request is there. You pay the
warm-up per request, and you should budget it explicitly.

Take the fixed cost plus per-method cost from the table and multiply by what you actually warm up.
A handful of specializations over small templates is a fraction of a millisecond. Thirty
specializations over 20-method templates is tens of milliseconds on every request, which is a real
share of an FPM budget and probably means the design wants revisiting.

The honest summary: **FPM works, and it is not what this is for.** If the warm-up matters at your
scale, a worker runtime removes it entirely.

## opcache preload

> **Specializing during `opcache.preload` is not supported.** Measured on PHP 8.4 it does not
> quietly fail - `Generic::warmUp()` inside a preload script **segfaults**, so the server does
> not start at all. `tests/Runtime/PreloadTest.php` asserts that a specialization built during
> preload never reaches the following request, however it fails.

**Why.** The preload request is a request. Its allocations are released when it ends, so a class
entry built there cannot survive into the requests that follow. The engine does not survive the
attempt either: what it actually does is crash during preload, which at least means the mistake
announces itself rather than leaving a class mysteriously absent later.

**What does work, and is worth doing:** preloading the **templates**. A preloaded template is
compiled once at server start and shared by every worker, so the specialization that happens at
request time starts from a class entry that is already in memory. Preloading z-engine's own
definitions is the same kind of win and now happens by itself — its Composer bootstrap recognises
the preload stage and publishes the engine definitions for the life of the server — which is why
the `preload.php` this repository ships needs to do nothing but require the autoloader and compile
your templates:

```ini
opcache.preload = /path/to/vendor/lisachenko/userland-php-generics/preload.php
opcache.preload_user = www-data
```

Read that file before pointing your ini at it — the commented block at the bottom is where your
own templates go.

## Memory, and what "leak" means here

There is no leak in the ordinary sense. Everything a specialization allocates comes from request
memory and is released at request end, whether or not anything went wrong.

What there *is* is unbounded growth within a single long-lived worker if you mint without bound.
A thousand specializations of a four-method template is 5.9 MiB — a fine number for a known set,
and an alarming one for a set that grows with traffic. The bound is your responsibility: warm up
a list you wrote down.

z-engine's own [`docs/long-running.md`](https://github.com/lisachenko/z-engine/blob/master/docs/long-running.md)
covers a different question — who owns each byte the engine hands back, and how wrappers release
it. Worth reading if you are extending the package rather than using it.

## Checklist

- [ ] `Generic::bootstrap()` at start-up, so a bad host fails loudly and early
- [ ] `Generic::warmUp()` with a written-down list, at worker boot
- [ ] No specialization on request input
- [ ] No `reset()` in the request loop
- [ ] Templates in `opcache.preload`; specializations **not**
- [ ] On FPM, the warm-up cost measured against the request budget you actually have

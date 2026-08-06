# Contributing

Thanks for wanting to help. This is the short human version — [`AGENTS.md`](AGENTS.md) is the full
contract for both humans and automated tools, and it defers to
[z-engine's `AGENTS.md`](https://github.com/lisachenko/z-engine/blob/master/AGENTS.md) for everything
about the engine itself.

## Before you start

- **Match your PHP version to the branch.** Engine struct layouts are version-specific; this package
  tracks one PHP minor at a time.
- **Develop against a debug build** (`--enable-debug`, FFI on). It turns silent memory corruption
  into loud assertion failures.
- FFI must be enabled (`ffi.enable=1`) and the JIT disabled (`opcache.jit=off`).

## Setup

```bash
composer install
php -d ffi.enable=1 -d opcache.jit=off vendor/bin/phpunit   # or: composer test
composer phpstan
composer cs:check
composer test:internal   # destructive class-table eviction; a debug build also reports leaks
composer test:preload    # boots preload.php in a child process, needs opcache
```

`composer test:analysis` runs only the static-analysis tests and needs neither FFI nor a matching
PHP build, so you can work on the PHPStan extension anywhere.

## Making changes

1. Keep the code clean at PHPStan level max and at `cs:check`.
2. Never read a doc comment in the runtime path — attributes are the runtime source of truth
   (see `AGENTS.md` §2). The `tests-opcache` CI job enforces this.
3. Validate everything *before* calling into the engine, so a rejection can never leave a
   half-registered class behind.
4. Add a static named constructor for every new failure mode instead of an inline `throw`.
5. Give every test that registers a specialization a unique target name.
6. Add or update tests, and update `docs/` when behaviour changes — `design.md` for how the
   engine is driven, `static-analysis.md` for the extension.
7. If you touch a template fixture or the stub generator, run `composer stubs:generate`. The
   stubs under `tests/phpstan/generated/` are committed and `composer stubs:check` runs in CI,
   so hand-editing one works only until the next run throws the edit away.
8. Never hand-edit `docs/benchmarks.md` — it is written by `composer bench`, and every sentence
   under a table is computed from that run. If a number needs explaining, explain it in the
   scenario that produces it.
9. Use [Conventional Commits](https://www.conventionalcommits.org/).

## Pull request checklist

- [ ] `composer test` passes on the matching PHP minor
- [ ] `composer phpstan` and `composer cs:check` are green
- [ ] `composer stubs:check` is green if you touched a template or the generator
- [ ] tests added or updated, with unique specialized class names
- [ ] docs updated if behaviour changed
- [ ] conventional commit messages

## Reporting bugs

Include the first line of `php -v`, whether the build is NTS or ZTS, your OS and architecture, the
installed z-engine version, and a minimal template class that reproduces the problem.

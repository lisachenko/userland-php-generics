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

/*
 * Static-analysis-only declarations (phpstan.dist.neon scanFiles) for symbols that do
 * NOT exist at runtime by design:
 *
 *  - the placeholder types the placeholder-form fixtures declare as native types. The
 *    whole point is that they are never defined, so the engine rejects every assignment
 *    on the unspecialized template.
 *
 * This file lives outside the PSR-4 layout, so the autoloader can never load it: the
 * declarations are visible to PHPStan only.
 */

declare(strict_types=1);

namespace Lisachenko\Generics\Fixture;

class T {}

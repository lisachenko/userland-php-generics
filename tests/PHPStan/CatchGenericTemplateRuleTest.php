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

namespace Lisachenko\Generics\PHPStan;

use Lisachenko\Generics\PHPStan\Rule\CatchGenericTemplateRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<CatchGenericTemplateRule>
 */
final class CatchGenericTemplateRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new CatchGenericTemplateRule($this->createReflectionProvider());
    }

    public function testCatchingATemplateIsReported(): void
    {
        $this->analyse([__DIR__ . '/data/instanceof-template.php'], [
            [
                'Catching the generic template Lisachenko\Generics\PHPStan\Data\GoodBox never '
                . 'matches one of its specializations.',
                33,
                "A specialization is a sibling of its template, not a subclass.\n"
                . 'Catch an interface the template implements instead.',
            ],
        ]);
    }
}

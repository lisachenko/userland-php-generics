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

use Lisachenko\Generics\PHPStan\Rule\InstanceofGenericTemplateRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<InstanceofGenericTemplateRule>
 */
final class InstanceofGenericTemplateRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new InstanceofGenericTemplateRule($this->createReflectionProvider());
    }

    public function testOnlyATemplateIsReported(): void
    {
        // The negative cases carry the rule's value as much as the positive one: reporting
        // every instanceof would just teach people to ignore it
        $this->analyse([__DIR__ . '/data/instanceof-template.php'], [
            [
                'Instanceof between object and the generic template '
                . 'Lisachenko\Generics\PHPStan\Data\GoodBox is always false.',
                14,
                "A specialization is a sibling of its template, not a subclass.\n"
                . 'Type-hint an interface the template implements, or ask '
                . 'Generic::isSpecialization($value, Lisachenko\Generics\PHPStan\Data\GoodBox::class).',
            ],
        ]);
    }
}

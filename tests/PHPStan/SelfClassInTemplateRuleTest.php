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

use Lisachenko\Generics\PHPStan\Rule\SelfClassInTemplateRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<SelfClassInTemplateRule>
 */
final class SelfClassInTemplateRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new SelfClassInTemplateRule();
    }

    public function testOnlySelfClassInsideATemplateIsReported(): void
    {
        // static::class in the same class and self::class in a non-template are both left
        // alone, which is what keeps the rule from becoming noise
        $this->analyse([__DIR__ . '/data/self-class.php'], [
            [
                'self::class inside the generic template '
                . 'Lisachenko\Generics\PHPStan\Data\SelfClassCase\Describing resolves to the '
                . 'template, not to the specialization the method is running on.',
                16,
                "Method bodies are shared with the template, and self::class was folded into\n"
                . 'them at compile time. Use static::class, which is late-bound.',
            ],
        ]);
    }
}

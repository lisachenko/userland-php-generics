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

namespace Lisachenko\Generics\PHPStan\Rule;

use Lisachenko\Generics\PHPStan\TemplateReflection;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\PhpDoc\Tag\TemplateTag;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\VerbosityLevel;

/**
 * Keeps `#[TemplateParameter]` and `@template` from drifting apart
 *
 * This package deliberately has two sources of truth, because neither one can do the other's
 * job: the **attribute** is what the runtime reads, since `opcache.save_comments=0` makes doc
 * comments NULL in production; the **doc tag** is what PHPStan and the IDE read, since an
 * attribute means nothing to them. Nothing in the language keeps the pair in step.
 *
 * That makes this rule the seam. Without it, deleting a `@template` leaves a template that
 * still specializes at run time but analyses as non-generic, and renaming one leaves
 * substitution silently looking for a parameter nobody declares.
 *
 * @implements Rule<InClassNode>
 */
final class TemplateParameterConsistencyRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class      = $node->getClassReflection();
        $attributes = TemplateReflection::typeParameterNames($class);
        $tags       = array_keys($class->getTemplateTags());

        if ($attributes === [] && $tags === []) {
            return [];
        }

        if ($attributes === []) {
            // A `@template` with no attribute is ordinary PHPStan-only generics, which is a
            // perfectly reasonable thing to write and none of this rule's business
            return [];
        }

        if ($tags === []) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Generic template %s declares #[TemplateParameter] but no @template tag, so '
                    . 'static analysis cannot see that it is generic.',
                    $class->getName(),
                ))
                    ->identifier('generics.missingTemplateTag')
                    ->tip(sprintf('Add "@template %s" to the class doc comment.', implode("\n@template ", $attributes)))
                    ->build(),
            ];
        }

        if ($attributes !== $tags) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Generic template %s declares type parameters %s as attributes but %s as '
                    . '@template tags; the runtime reads the attributes and static analysis '
                    . 'reads the tags, so they have to agree in name and order.',
                    $class->getName(),
                    implode(', ', $attributes),
                    implode(', ', $tags),
                ))
                    ->identifier('generics.templateParameterMismatch')
                    ->build(),
            ];
        }

        return $this->checkBounds($class->getName(), TemplateReflection::bounds($class), $class->getTemplateTags());
    }

    /**
     * @param  array<string, string>       $bounds Parameter name => bound declared on the attribute
     * @param  array<string, TemplateTag>  $tags   Parameter name => PHPStan template tag
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function checkBounds(string $className, array $bounds, array $tags): array
    {
        $errors = [];
        foreach ($bounds as $parameter => $bound) {
            $tag = $tags[$parameter] ?? null;
            if ($tag === null) {
                continue;
            }

            $declared = $tag->getBound()->describe(VerbosityLevel::typeOnly());

            // PHPStan writes an unbounded parameter as `mixed`, which is how "no `of X`" reads
            if ($declared === 'mixed' || ltrim($declared, '\\') === ltrim($bound, '\\')) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Type parameter %s of generic template %s is bounded by %s in its attribute but '
                . 'by %s in its @template tag.',
                $parameter,
                $className,
                $bound,
                $declared,
            ))
                ->identifier('generics.templateBoundMismatch')
                ->build();
        }

        return $errors;
    }
}

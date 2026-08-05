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

namespace Lisachenko\Generics\Template;

/**
 * Memoizes parsed template definitions for the lifetime of the process
 *
 * Parsing is pure reflection over a declaration that cannot change, so the first
 * `Box::of('int')` pays for it and every later call does not.
 */
final class TemplateRegistry
{
    /**
     * @var array<class-string, TemplateDefinition>
     */
    private array $definitions = [];

    public function __construct(private readonly TemplateParser $parser = new TemplateParser()) {}

    /**
     * @param class-string $className
     */
    public function definitionOf(string $className): TemplateDefinition
    {
        return $this->definitions[$className] ??= $this->parser->parse($className);
    }

    public function forget(): void
    {
        $this->definitions = [];
    }
}

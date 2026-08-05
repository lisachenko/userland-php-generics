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

namespace Lisachenko\Generics\StubGenerator;

/**
 * One rendered stub, ready to be written next to the others
 */
final class GeneratedStub
{
    /**
     * @param class-string $templateName
     * @param list<string> $placeholderNames Fictional types this template's declarations name
     */
    public function __construct(
        public readonly string $templateName,
        public readonly string $namespace,
        public readonly string $classDeclaration,
        public readonly array $placeholderNames,
    ) {}

    /**
     * The file name this stub must be written to
     *
     * Derived from the template rather than chosen, because one class per file is a hard
     * PHPStan constraint rather than a style preference - it indexes only the first class
     * declaration in a stub file.
     */
    public function fileName(): string
    {
        $shortName = strrchr($this->templateName, '\\');

        return sprintf('%s-stub.php', strtolower(ltrim($shortName === false ? $this->templateName : $shortName, '\\')));
    }

    public function render(): string
    {
        return sprintf(
            "%s\ndeclare(strict_types=1);\n\nnamespace %s;\n\n%s",
            StubFileHeader::forStub($this->templateName),
            $this->namespace,
            $this->classDeclaration,
        );
    }
}

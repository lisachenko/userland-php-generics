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

namespace Lisachenko\Generics\Benchmark\Fixture;

/**
 * What kind of substituted slot the synthesized methods carry
 *
 * This is the independent variable of the whole harness. Each case costs the engine something
 * different per specialization, and separating them is what makes the numbers mean anything:
 * lumping them together would average a constant with something that scales with body size.
 */
enum MethodSlot: string
{
    /**
     * No substituted slot on any method - only the property is specialized
     *
     * The floor: a method contributes one `zend_op_array` struct and nothing else, because its
     * `arg_info` block and its opcodes both stay shared with the template.
     */
    case None = 'none';

    /**
     * A `mixed` parameter that is not a slot, over a body that writes the substituted property
     *
     * Isolates the property check: nothing about the signature is specialized, so whatever this
     * costs is what a typed property write costs.
     */
    case PropertyOnly = 'property-only';

    /**
     * A placeholder-typed parameter (`T $value`)
     *
     * The check reads `arg_info` through the generic `ZEND_RECV` path, so the method duplicates
     * its `arg_info` block and keeps sharing its opcodes.
     */
    case ClassParameter = 'class-parameter';

    /**
     * A `mixed` parameter marked with `#[Of]`
     *
     * `ZEND_RECV` tests a type mask the compiler cached into the opline, so the method has to
     * un-share its opcode array as well - the one case whose cost scales with body size.
     */
    case BuiltinParameter = 'builtin-parameter';
}

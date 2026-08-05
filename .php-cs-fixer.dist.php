<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0'             => true,
        'declare_strict_types'   => true,
        'ordered_imports'        => ['sort_algorithm' => 'alpha'],
        'no_unused_imports'      => true,
        'single_quote'           => true,
        'array_syntax'           => ['syntax' => 'short'],
        'binary_operator_spaces' => ['default' => 'align_single_space_minimal'],
    ])
    ->setFinder($finder);

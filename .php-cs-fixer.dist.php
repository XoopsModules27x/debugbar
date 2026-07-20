<?php

declare(strict_types=1);

/**
 * PHP-CS-Fixer configuration — XOOPS module DevOps baseline.
 *
 * Single source of truth for code style across all XOOPS modules.
 * (StyleCI and Pint are intentionally NOT used; CS-Fixer is the one engine.)
 *
 * Overlay version: 1.1.0
 * xoops-overlay:profile=core27
 *
 * @see https://cs.symfony.com
 */

$paths = array_filter([
    __DIR__ . '/admin',
    __DIR__ . '/blocks',
    __DIR__ . '/class',
    __DIR__ . '/include',
    __DIR__ . '/preloads',
    __DIR__ . '/src',
    __DIR__ . '/tests',
], is_dir(...));

$finder = (new PhpCsFixer\Finder())
    ->in($paths ?: [__DIR__])
    ->exclude(['vendor', '.build', 'node_modules'])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12'                       => true,
        '@PSR12:risky'                 => true,
        // XOOPS house style: keep `<?php declare(strict_types=1);` on ONE line.
        // PSR-12 would split it; disabling these two opening-tag rules preserves the
        // one-liner, while `declare_strict_types` below still enforces/adds it inline.
        'blank_line_after_opening_tag' => false,
        'linebreak_after_opening_tag'  => false,
        'array_syntax'                 => ['syntax' => 'short'],
        'binary_operator_spaces'       => ['default' => 'single_space'],
        'blank_line_before_statement'  => ['statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try']],
        'cast_spaces'                  => ['space' => 'single'],
        'class_attributes_separation'  => ['elements' => ['method' => 'one']],
        'concat_space'                 => ['spacing' => 'one'],
        'declare_strict_types'         => true,
        'method_argument_space'        => ['on_multiline' => 'ensure_fully_multiline'],
        'no_unused_imports'            => true,
        'not_operator_with_successor_space' => true,
        'ordered_imports'              => ['sort_algorithm' => 'alpha'],
        'phpdoc_scalar'                => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_var_without_name'      => true,
        'single_quote'                 => true,
        'single_trait_insert_per_statement' => true,
        'trailing_comma_in_multiline'  => true,
        'unary_operator_spaces'        => true,
    ])
    ->setFinder($finder);

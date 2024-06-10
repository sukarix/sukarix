<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->files()
    ->name(['*.php'])
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests');

$config = new PhpCsFixer\Config();
$config
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP83Migration' => true,
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        'general_phpdoc_annotation_remove' => ['annotations' => ['expectedDeprecation']], // one should use PHPUnit built-in method instead
        'no_empty_comment' => true,
        'no_trailing_whitespace_in_comment' => true,
        'ordered_interfaces' => true,
        'concat_space' => ['spacing' => 'one'],
        'function_declaration' => ['closure_function_spacing' => 'none'],
        'constant_case' => ['case' => 'lower'],
        'single_quote' => true,
        'mb_str_functions' => true,
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => ['operators' =>
            ['=>' => 'align_single_space_minimal', '=' => 'align_single_space_minimal']
        ],
    ])
    ->setFinder($finder);

return $config;

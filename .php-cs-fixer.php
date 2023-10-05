<?php

$config = new PhpCsFixer\Config();
$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor']);

return $config
    ->setRules([
        '@PSR12'                                 => true,
        'new_with_braces'                        => false,
        'array_indentation'                      => true,
        'array_syntax'                           => ['syntax' => 'short'],
        'combine_consecutive_unsets'             => true,
        'multiline_whitespace_before_semicolons' => false,
        'single_quote'                           => true,
        'blank_line_before_statement'            => true,
        'braces'                                 => [
            'allow_single_line_closure' => true,
        ],
        // no multiple blank lines
        'no_extra_blank_lines' => [
            'tokens' => [
                'extra',
                'break',
                'continue',
                'curly_brace_block',
                'parenthesis_brace_block',
                'return',
                'square_brace_block',
                'throw',
                'use',
                'use_trait',
            ],
        ],
        'concat_space'                                => ['spacing' => 'none'],
        'declare_equal_normalize'                     => true,
        'function_typehint_space'                     => true,
        'include'                                     => true,
        'lowercase_cast'                              => true,
        'no_multiline_whitespace_around_double_arrow' => true,
        'no_spaces_around_offset'                     => true,
        'no_unused_imports'                           => true,
        'no_whitespace_before_comma_in_array'         => true,
        'no_whitespace_in_blank_line'                 => true,
        'object_operator_without_whitespace'          => true,
        'blank_lines_before_namespace'                => true,
        'ternary_operator_spaces'                     => true,
        'trailing_comma_in_multiline'                 => true,
        'trim_array_spaces'                           => true,
        'unary_operator_spaces'                       => true,
        'binary_operator_spaces'                      => [
            'operators' => [
                '=>' => 'align_single_space_minimal',
            ],
        ],
        'whitespace_after_comma_in_array'   => true,
        'single_trait_insert_per_statement' => false,
    ])
    ->setLineEnding("\n")
    ->setFinder($finder)
    ->setUsingCache(false);

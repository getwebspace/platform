<?php
declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->in(__DIR__);

return PhpCsFixer\Config::create()
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setUsingCache(false)
    ->setRules([
        // basic
        'psr0'                         => true,
        '@PSR1'                        => true,
        '@PSR2'                        => true,
        'psr4'                         => true,
        '@PHP70Migration'              => true,
        '@PHP71Migration'              => true,
        '@PHP73Migration'              => true,
        '@DoctrineAnnotation'          => true,

        // other
        'strict_param'                 => true,
        'strict_comparison'            => true,
        'declare_strict_types'         => true,
        'array_indentation'            => true,
        'combine_consecutive_issets'   => true,
        'combine_consecutive_unsets'   => true,
        'mb_str_functions'             => true,
        'fully_qualified_strict_types' => false,
        'blank_line_after_opening_tag' => false,
        'blank_line_before_statement'  => [
            'statements' => [
               'break',
               'continue',
               'return',
               'throw',
               'try',
               'yield',
            ],
        ],
        'array_syntax'                 => ['syntax' => 'short'],
        'concat_space'                 => ['spacing' => 'one'],
        'method_argument_space' => false,

        // fix no
        'no_alias_functions' => true,
        'no_blank_lines_after_class_opening' => true,
        'no_blank_lines_after_phpdoc' => true,
        'no_blank_lines_before_namespace' => false, // we want 1 blank line before namespace
        'no_break_comment' => true,
        'no_closing_tag' => true,
        'no_empty_comment' => true,
        'no_empty_phpdoc' => true,
        'no_empty_statement' => true,
        'no_extra_blank_lines' => true,
        'no_homoglyph_names' => true,
        'no_leading_import_slash' => true,
        'no_leading_namespace_whitespace' => true,
        'no_mixed_echo_print' => true,
        'no_multiline_whitespace_around_double_arrow' => true,
        'no_multiline_whitespace_before_semicolons' => true,
        'non_printable_character' => true,
        'no_null_property_initialization' => true,
        'no_php4_constructor' => true,
        'no_unused_imports' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'no_short_bool_cast' => true,
        'no_short_echo_tag' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'no_spaces_after_function_name' => true,
        'no_spaces_around_offset' => true,
        'no_spaces_inside_parenthesis' => true,
        'no_superfluous_elseif' => true,
        'not_operator_with_space' => false, // No we prefer to keep '!' without spaces
        'not_operator_with_successor_space' => false, // idem
        'no_trailing_comma_in_list_call' => true,
        'no_trailing_comma_in_singleline_array' => true,
        'no_trailing_whitespace_in_comment' => true,
        'no_trailing_whitespace' => true,
        'no_unneeded_control_parentheses' => true,
        'no_unneeded_curly_braces' => true,
        'no_unneeded_final_method' => true,
        'no_unreachable_default_argument_value' => true,
        'no_whitespace_before_comma_in_array' => true,
        'no_whitespace_in_blank_line' => true,

        // adds
        'ordered_class_elements' => false, // We prefer to keep some freedom
        'ordered_imports' => true,
        'class_keyword_remove' => false, // ::class keyword gives us beter support in IDE
        'return_type_declaration' => true,
        'self_accessor' => true,
        'short_scalar_cast' => true,
        'simplified_null_return' => false, // Even if technically correct we prefer to be explicit
        'single_blank_line_at_eof' => true,
        'single_blank_line_before_namespace' => true,
        'single_class_element_per_statement' => true,
        'single_import_per_statement' => true,
        'single_line_after_imports' => true,
        'single_line_comment_style' => true,
        'single_quote' => true,

        // php doc
        'phpdoc_add_missing_param_annotation' => true,
        'phpdoc_align' => false, // Waste of time
        'phpdoc_annotation_without_dot' => true,
        'phpdoc_indent' => true,
        'phpdoc_inline_tag' => true,
        'phpdoc_no_access' => true,
        'phpdoc_no_alias_tag' => true,
        'phpdoc_no_empty_return' => true,
        'phpdoc_no_package' => true,
        'phpdoc_no_useless_inheritdoc' => true,
        'phpdoc_order' => true,
        'phpdoc_return_self_reference' => true,
        'phpdoc_scalar' => true,
        'phpdoc_separation' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => true,
        'phpdoc_trim' => true,
        'phpdoc_types_order' => true,
        'phpdoc_types' => true,
        'phpdoc_var_without_name' => true,
   ]);

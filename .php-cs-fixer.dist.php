<?php declare(strict_types = 1);

/*
 * This file is part of the Valksor package.
 *
 * (c) Dāvis Zālītis (k0d3r1s) <packages@valksor.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$fileHeaderComment = <<<'EOF'
    This file is part of the Valksor package.

    (c) Davis Zalitis (k0d3r1s)
    (c) SIA Valksor <packages@valksor.com>

    For the full copyright and license information, please view the LICENSE
    file that was distributed with this source code.
    EOF;

$finder = Finder::create()
    ->in([getcwd(), ])
    ->exclude(['vendor', 'var', '.github', ]);

$fixers = new ValksorDev\PhpCsFixerCustomFixers\Fixers();

return new Config()
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->registerCustomFixers($fixers)
    ->setRules([
        ...$fixers::getFixers(),
        '@PER-CS3x0' => true,
        '@PER-CS3x0:risky' => true,
        '@PHP8x0Migration:risky' => true,
        '@PHP8x1Migration' => true,
        '@PHP8x2Migration' => true,
        '@PHP8x3Migration' => true,
        '@PHP8x4Migration' => true,
        '@PHP8x5Migration' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'binary_operator_spaces' => true,
        'blank_line_after_opening_tag' => false,
        'blank_line_before_statement' => ['statements' => ['break', 'case', 'continue', 'declare', 'default', 'do', 'exit', 'for', 'foreach', 'goto', 'if', 'include', 'include_once', 'phpdoc', 'require', 'require_once', 'return', 'switch', 'throw', 'try', 'while', 'yield', 'yield_from', ], ],
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'comment_to_phpdoc' => true,
        'concat_space' => ['spacing' => 'one', ],
        'date_time_create_from_format_call' => true,
        'declare_equal_normalize' => ['space' => 'single', ],
        'declare_strict_types' => true,
        'explicit_indirect_variable' => true,
        'fopen_flags' => ['b_mode' => true, ],
        'fully_qualified_strict_types' => ['import_symbols' => true, ],
        'global_namespace_import' => ['import_constants' => true, 'import_functions' => true, 'import_classes' => true, ],
        'header_comment' => ['header' => $fileHeaderComment],
        'increment_style' => ['style' => 'post', ],
        'linebreak_after_opening_tag' => false,
        'magic_constant_casing' => true,
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline', 'keep_multiple_spaces_after_comma' => false, ],
        'method_chaining_indentation' => false,
        'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line', ],
        'native_constant_invocation' => false,
        'native_type_declaration_casing' => true,
        'new_with_parentheses' => ['anonymous_class' => false],
        'no_alias_functions' => ['sets' => ['@all', ], ],
        'no_blank_lines_after_class_opening' => true,
        'no_superfluous_elseif' => true,
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true, 'remove_inheritdoc' => true, ],
        'no_trailing_comma_in_singleline' => ['elements' => [], ],
        'no_unneeded_braces' => true,
        'no_useless_else' => true,
        'no_useless_sprintf' => true,
        'ordered_class_elements' => ['sort_algorithm' => 'alpha', 'case_sensitive' => true, 'order' => ['use_trait', 'public', 'protected', 'private', 'case', 'constant', 'constant_public', 'constant_protected', 'constant_private', 'property', 'property_static', 'property_public', 'property_public_readonly', 'property_public_static', 'property_protected', 'property_protected_readonly', 'property_protected_static', 'property_private', 'property_private_readonly', 'property_private_static', 'construct', 'destruct', 'magic', 'method', 'method_abstract', 'method_static', 'method_public_abstract', 'method_public_abstract_static', 'method_public', 'method_public_static', 'method_protected_abstract', 'method_protected_abstract_static', 'method_protected', 'method_protected_static', 'method_private_abstract', 'method_private_abstract_static', 'method_private', 'method_private_static'], ],
        'ordered_imports' => ['sort_algorithm' => 'alpha', 'imports_order' => ['class', 'function', 'const', ], ],
        'protected_to_private' => false,
        'return_assignment' => true,
        'simple_to_complex_string_variable' => true,
        'single_line_empty_body' => false,
        'single_line_throw' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters', 'match', ], ],
        'types_spaces' => ['space' => 'none', ],
        'yoda_style' => true,
    ])
    ->setRiskyAllowed(true)
    ->setCacheFile(getcwd() . '/.php-cs-fixer.cache')
    ->setFinder($finder)
    ->setUnsupportedPhpVersionAllowed(true);

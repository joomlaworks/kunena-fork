<?php

/**
 * PHP CS Fixer configuration.
 *
 * Usage (from the repository root):
 *   php-cs-fixer fix              # apply
 *   php-cs-fixer fix --dry-run -v # check
 *
 * The code base must keep running on PHP 7.1 - 8.5, so only @PSR12 is used (no PER-CS / PHP 8 syntax
 * migrations, which would add e.g. trailing commas in argument lists).
 */

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->name('*.php')
    ->exclude(['vendor', 'node_modules'])
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        // PHP 8.4 deprecates implicitly nullable parameter types ("Foo $x = null")
        'nullable_type_declaration_for_default_null_value' => true,
    ])
    ->setFinder($finder);

<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var');

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PSR12' => true,
        'declare_strict_types' => true, // Enforce strict types everywhere
        'strict_param' => true, // Force strict comparison in array functions
        'array_syntax' => ['syntax' => 'short'],
        'void_return' => true, // Add void return type where applicable
        'mb_str_functions' => true, // Use mb_ string functions
        'no_superfluous_phpdoc_tags' => true, // Remove redundant phpdoc
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
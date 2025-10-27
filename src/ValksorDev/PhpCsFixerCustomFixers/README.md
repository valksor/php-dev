# Valksor PHP-CS-Fixer Custom Fixers

A comprehensive PHP library providing custom fixers for PHP-CS-Fixer to enhance code quality, enforce modern coding standards, and improve development workflow efficiency.

## Use Cases

### Code Quality Enhancement
- **Modern PHP Standards**: Enforce PHP 8.4+ best practices including promoted constructor properties
- **Readability Improvements**: Automatic formatting for better code readability and maintainability
- **Team Consistency**: Ensure consistent coding style across development teams
- **Migration Cleanup**: Automatically clean up auto-generated comments in Doctrine migration files

### Development Workflow Automation
- **Automated Refactoring**: Convert traditional constructor patterns to modern promoted properties
- **Code Cleanup**: Remove unnecessary function calls and redundant code patterns
- **Standards Enforcement**: Go beyond PSR-12 with project-specific coding standards
- **CI/CD Integration**: Seamless integration with continuous integration pipelines

### Performance Optimization
- **Code Optimization**: Remove inefficient patterns like unnecessary `strlen()` calls in comparisons
- **Clean Code**: Eliminate redundant `dirname()` calls and other unnecessary constructs
- **Modern Patterns**: Promote use of modern PHP features for better performance

## Installation

Install the package via Composer:

```bash
composer require valksor/php-cs-fixer-custom-fixers --dev
```

## Requirements

- **PHP 8.4 or higher**
- **friendsofphp/php-cs-fixer** (3.81.0 or higher)
- **symfony/finder** (7.0 or higher)
- **valksor/functions-preg** (^1.0)

### Optional Dependencies

- **doctrine/migrations**: For fixing migration file comments (suggested)

## Usage

The package provides custom fixers that can be used with PHP-CS-Fixer to automatically fix code style issues.

### Basic Usage

```php
use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use ValksorDev\PhpCsFixerCustomFixers\Fixers;

$finder = Finder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    ->name('*.php');

$config = new Config();
$config
    ->setRules([
        '@PSR12' => true,
        // Enable all Valksor custom fixers
        'ValksorPhpCsFixerCustomFixers/declare_after_opening_tag' => true,
        'ValksorPhpCsFixerCustomFixers/doctrine_migrations' => true,
        'ValksorPhpCsFixerCustomFixers/line_break_between_method_arguments' => true,
        'ValksorPhpCsFixerCustomFixers/line_break_between_statements' => true,
        'ValksorPhpCsFixerCustomFixers/no_useless_dirname_call' => true,
        'ValksorPhpCsFixerCustomFixers/no_useless_strlen' => true,
        'ValksorPhpCsFixerCustomFixers/promoted_constructor_property' => true,
    ])
    ->setFinder($finder);

// Register custom fixers
$fixers = new Fixers();
foreach ($fixers as $fixer) {
    $config->registerCustomFixers([$fixer]);
}

return $config;
```

### Registering Specific Fixers

If you only want to use specific fixers, you can register them individually:

```php
use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use ValksorDev\PhpCsFixerCustomFixers\Fixer\DoctrineMigrationsFixer;

$finder = Finder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    ->name('*.php');

$config = new Config();
$config
    ->setRules([
        '@PSR12' => true,
        // Enable only specific Valksor custom fixers
        'ValksorPhpCsFixerCustomFixers/doctrine_migrations' => true,
    ])
    ->setFinder($finder)
    ->registerCustomFixers([
        new DoctrineMigrationsFixer(),
    ]);

return $config;
```

## Available Fixers

### DeclareAfterOpeningTagFixer

Ensures that `declare(strict_types = 1)` is placed immediately after the opening PHP tag.

### DoctrineMigrationsFixer

Removes unnecessary auto-generated comments from Doctrine migration files.

```php
// Before
/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230101000000 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
    }
}

// After
final class Version20230101000000 extends AbstractMigration
{
    public function up(Schema $schema)
    {
    }
}
```

### LineBreakBetweenMethodArgumentsFixer

Ensures that method arguments are separated by line breaks when there are multiple arguments.

### LineBreakBetweenStatementsFixer

Ensures that statements are separated by line breaks for better readability.

### NoUselessDirnameCallFixer

Removes unnecessary `dirname()` calls.

### NoUselessStrlenFixer

Removes unnecessary `strlen()` calls when used in comparisons.

### PromotedConstructorPropertyFixer

Converts traditional constructor property assignments to promoted properties (PHP 8.0+).

## License

This package is licensed under the [BSD-3-Clause License](LICENSE).

## About Valksor

This package is part of the [valksor/valksor](https://github.com/valksor/valksor) project - a comprehensive PHP library and Symfony bundle that provides a collection of utilities, components, and integrations for Symfony applications.

The main project includes:
- Various utility functions and components
- Doctrine ORM tools and extensions
- Symfony bundle for easy configuration
- And much more

If you find these custom fixers useful, you might want to check out the full Valksor project for additional tools and utilities that can enhance your Symfony application development.

To install the complete package:

```bash
composer require valksor/valksor
```

# Valksor PHP Dev

[![BSD-3-Clause](https://img.shields.io/badge/BSD--3--Clause-green?style=flat)](https://github.com/valksor/php-dev/blob/master/LICENSE)
[![Coverage Status](https://coveralls.io/repos/github/valksor/php-dev/badge.svg?branch=master)](https://coveralls.io/github/valksor/php-dev?branch=master)

## This repository contains these:

<table>
<tr>
<th>Repository</th>
<th>Coverage</th>
<th>Repository</th>
<th>Coverage</th>
</tr>
<tr>
<td><a href="https://github.com/valksor/php-dev-build">php-dev-build</a></td>
<td><a href="https://coveralls.io/github/valksor/php-dev-build?branch=master"><img src="https://coveralls.io/repos/github/valksor/php-dev-build/badge.svg?branch=master" alt="Coverage"></a></td>
<td><a href="https://github.com/valksor/php-dev-cs-fixer-custom-fixers">php-dev-cs-fixer-custom-fixers</a></td>
<td><a href="https://coveralls.io/github/valksor/php-dev-cs-fixer-custom-fixers?branch=master"><img src="https://coveralls.io/repos/github/valksor/php-dev-cs-fixer-custom-fixers/badge.svg?branch=master" alt="Coverage"></a></td>
</tr>
</table>

A comprehensive PHP development toolkit that provides modern development tools, custom PHP-CS-Fixer fixers, build automation, and hot reloading capabilities for Symfony applications. This library enhances development workflow efficiency with integrated tooling for frontend builds, code quality, and real-time development features.

## Features

- **Build Tools**: Hot reloading, asset compilation, import map management, and development workflow automation
- **Custom PHP-CS-Fixer Fixers**: Enhanced code quality enforcement with modern PHP 8.4+ best practices
- **Hot Reloading**: Automatic browser reload on file changes using inotify
- **Asset Management**: Integrated support for ESBuild, Tailwind CSS, and DaisyUI
- **Binary Management**: Unified binary download and version management for build tools
- **Process Orchestration**: Coordinated execution of multiple development services
- **SSE Integration**: Seamless integration with Server-Sent Events for live updates

## Requirements

- **PHP 8.4 or higher**
- **inotify extension** (for file watching)
- **PCNTL extension** (for process management)
- **POSIX extension**
- **friendsofphp/php-cs-fixer** (3.81.0 or higher)
- **symfony/framework-bundle** (7.2.0 or higher)
- **Valksor Bundle** and related components

## Installation

Install the package via Composer:

```bash
composer require valksor/php-dev
```

This meta-package automatically includes:
- `valksor/php-dev-build` - Build tools and hot reloading
- `valksor/php-dev-cs-fixer-custom-fixers` - Custom PHP-CS-Fixer fixers

## Basic Usage

### Quick Setup

1. Register the bundle in your Symfony application:

```php
// config/bundles.php
return [
    // ...
    Valksor\Bundle\ValksorBundle::class => ['all' => true],
    // ...
];
```

2. Enable the build tools and configure:

```yaml
# config/packages/valksor.yaml
valksor:
    build:
        enabled: true
        hot_reload:
            enabled: true
            watch_paths:
                - 'templates/'
                - 'src/'
                - 'assets/'
```

### Start Development Environment

```bash
# Start all development tools (hot reload, asset compilation, etc.)
php bin/console valksor:watch
```

### Configure PHP-CS-Fixer

```php
// .php-cs-fixer.php
use PhpCsFixer\Config;
use ValksorDev\PhpCsFixerCustomFixers\Fixers;

$config = new Config();
$config
    ->setRules([
        '@PSR12' => true,
        'ValksorPhpCsFixerCustomFixers/declare_after_opening_tag' => true,
        'ValksorPhpCsFixerCustomFixers/promoted_constructor_property' => true,
        // Add more custom fixers as needed
    ]);

// Register custom fixers
$fixers = new Fixers();
foreach ($fixers as $fixer) {
    $config->registerCustomFixers([$fixer]);
}

return $config;
```

## Documentation

- **[Build Tools Documentation](src/ValksorDev/Build/README.md)** - Complete guide for build tools, hot reloading, and asset management
- **[Custom Fixers Documentation](src/ValksorDev/PhpCsFixerCustomFixers/README.md)** - All available PHP-CS-Fixer custom fixers and their usage
- **[API Reference](src/ValksorDev/)** - Full API documentation and examples
- **[Configuration Guide](https://github.com/valksor/valksor-dev/wiki)** - Detailed configuration options

## Advanced Usage

### Custom Configuration

```yaml
valksor:
    build:
        enabled: true
        hot_reload:
            enabled: true
            watch_paths:
                - 'templates/'
                - 'src/Controller/'
                - 'assets/js/'
            exclude_patterns:
                - 'vendor/'
                - 'var/'
            debounce_ms: 100
            sse_port: 8080
        tailwind:
            enabled: true
            input: 'assets/css/app.css'
            output: 'public/build/app.css'
            minify: false
        binaries:
            download_dir: 'bin/build-tools/'
            esbuild_version: 'latest'
            tailwind_version: 'latest'
```

### Development Workflow Integration

The toolkit integrates seamlessly with modern Symfony development workflows:

- **Real-time Development**: Automatic browser reload on template, CSS, JavaScript, and PHP changes
- **Asset Compilation**: Integrated Tailwind CSS compilation with DaisyUI components
- **Import Maps**: Automatic JavaScript import map generation and synchronization
- **Code Quality**: Enhanced PHP-CS-Fixer with modern PHP 8.4+ best practices


## Contributing

Contributions are welcome!

- Code style requirements (PSR-12)
- Testing requirements for PRs
- One feature per pull request
- Development setup instructions

To contribute:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## Security

If you discover any security-related issues, please email us at packages@valksor.com instead of using the issue tracker.

## Support

- **Documentation**: [Full documentation](https://github.com/valksor/valksor-dev)
- **Issues**: [GitHub Issues](https://github.com/valksor/valksor-dev/issues) for bug reports and feature requests
- **Discussions**: [GitHub Discussions](https://github.com/valksor/valksor-dev/discussions) for questions and community support
- **Stack Overflow**: Use tag `valksor-php-dev`

## Credits

- **[Original Author](https://github.com/valksor)** - Creator and maintainer
- **[All Contributors](https://github.com/valksor/valksor-dev/graphs/contributors)** - Thank you to all who contributed
- **[Inspiration](https://github.com/friendsofphp/php-cs-fixer)** - Inspired by PHP-CS-Fixer ecosystem
- **[Valksor Project](https://github.com/valksor)** - Part of the larger Valksor PHP ecosystem

## License

This package is licensed under the [BSD-3-Clause License](LICENSE).

# Valksor PHP Dev

[![valksor](https://badgen.net/static/org/valksor/green)](https://github.com/valksor) 
[![BSD-3-Clause](https://img.shields.io/badge/BSD--3--Clause-green?style=flat)](https://github.com/valksor/php-dev/blob/master/LICENSE)
[![Coverage Status](https://coveralls.io/repos/github/valksor/php-dev/badge.svg?branch=master)](https://coveralls.io/github/valksor/php-dev?branch=master)
[![php](https://badgen.net/static/php/>=8.4/purple)](https://www.php.net/releases/8.4/en.php)

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
<tr>
<td><a href="https://github.com/valksor/php-dev-snapshot">php-dev-snapshot</a></td>
<td><a href="https://coveralls.io/github/valksor/php-dev-snapshot?branch=master"><img src="https://coveralls.io/repos/github/valksor/php-dev-snapshot/badge.svg?branch=master" alt="Coverage"></a></td>
</tr>
</table>

Modern development toolkit for Symfony applications that provides hot reloading, asset compilation, and automated build tools to speed up your development workflow.

## Why Use Valksor Dev?

**Save Time**: Automatic browser reload when you change files - no more manual refreshing
**Modern Tooling**: Integrated Tailwind CSS, ESBuild, and import map management
**Zero Configuration**: Works out of the box with sensible defaults
**Developer Experience**: Lightweight dev mode for quick feedback, full mode for comprehensive development
**Production Ready**: Optimized asset building for deployment

## Quick Start

Install the package:

```bash
composer require valksor/php-dev
```

Enable the bundle in your Symfony application:

```php
// config/bundles.php
return [
    Valksor\Bundle\ValksorBundle::class => ['all' => true],
];
```

Start development:

```bash
# Lightweight development (hot reload + SSE)
php bin/console valksor:dev

# Full development environment (all services)
php bin/console valksor:watch

# Build production assets
php bin/console valksor-prod:build
```

## Basic Configuration

For most projects, the simple configuration is all you need:

```yaml
# config/packages/valksor.yaml
valksor:
    build:
        enabled: true
        hot_reload:
            enabled: true
        tailwind:
            enabled: true
        importmap:
            enabled: true
```

### When to Use Simple Configuration

Use the basic setup above when:

- **Standard Symfony project** with single app structure
- **Default locations** for assets (`assets/`, `templates/`, `src/`)
- **Beginning development** and want to get started quickly
- **Most common use cases** - hot reload, Tailwind CSS, and import maps

### When to Use Advanced Configuration

Switch to advanced service configuration only when you need:

```yaml
# config/packages/valksor.yaml
valksor:
    build:
        services:
            binaries:
                enabled: true
                flags: ["init", "dev", "prod"]
                options:
                    required: ["tailwindcss", "esbuild", "daisyui"]
            hot_reload:
                enabled: true
                flags: ["dev"]
                options:
                    debounce_delay: 0.3
                    watch_dirs: ["/src", "/templates"]
            tailwind:
                enabled: true
                flags: ["dev", "prod"]
                options:
                    minify: false # Auto-sets to true in prod
```

**Use advanced configuration when:**

- **Multi-app projects** with separate `apps/` directory
- **Custom file locations** for assets or templates
- **Specific performance tuning** (debounce delays, watch directories)
- **Selective service execution** (disable certain features)
- **Production optimization** (different settings per environment)

That's it! Valksor Dev will automatically:

- Watch your PHP, Twig, CSS, and JavaScript files
- Reload your browser when files change
- Compile Tailwind CSS automatically
- Manage JavaScript import maps
- Download necessary build tools

## Frontend Integration

### Browser Hot Reload Setup

To enable automatic browser reloading, add this to your base HTML template:

```twig
{# templates/base.html.twig #}
<!DOCTYPE html>
<html>
<head>
    <title>{% block title %}My App{% endblock %}</title>
    <link rel="stylesheet" href="{{ asset('styles/app.css') }}">
    {{ valksor_sse_importmap_definition(['head', 'app']) }}
    {{ valksor_sse_importmap_scripts(['head']) }}
</head>
<body>
{% block body %}{% endblock %}
{% block javascripts %}
    {{ valksor_sse_importmap_scripts(['app']) }}
    {{ include('@ValksorSse/sse.html.twig') }}
{% endblock %}
</body>
</html>
```

**What each part does:**

- `valksor_sse_importmap_definition()`: Sets up the import map and SSE connection
- `valksor_sse_importmap_scripts()`: Loads JavaScript modules for specific app sections
- `@ValksorSse/sse.html.twig`: Includes the SSE client that handles browser reloads

### Asset File Setup

Create your frontend files in these locations:

```bash
# CSS files (automatically compiled by Tailwind)
assets/styles/app.css

# JavaScript files (managed by import map system)
assets/js/app.js

# Twig templates (auto-reload on changes)
templates/base.html.twig
templates/index.html.twig

# Compiled assets (automatically generated)
public/styles/app.css
```

### Tailwind CSS Setup

1. Create your main CSS file:

```css
/* assets/styles/app.css */
@import "tailwindcss" source(none);
@source "../templates/**/*.html.twig";
@source "../js/*.js";

@theme {
  --color-primary: #3b82f6;
}

@utility "theme-switcher" {
  /* Custom utilities if needed */
}
```

2. The compiled CSS will be automatically available at `/styles/app.css`

### JavaScript Import Maps

Your JavaScript automatically gets import map support:

```javascript
// assets/js/app.js
import { hotwire } from '@hotwired/turbo';
import { stimulus } from '@hotwired/stimulus';

// These imports are automatically resolved by the import map system
// No need for manual script tags or complex bundling setup
```

## Commands

| Command                    | What It Does                            | When to Use                   |
| -------------------------- | --------------------------------------- | ----------------------------- |
| `valksor:dev`              | Lightweight dev mode (hot reload + SSE) | Quick frontend development    |
| `valksor:watch`            | Full dev environment (all services)     | Complete development workflow |
| `valksor-prod:build`       | Build production assets                 | Before deployment             |
| `valksor:tailwind`         | Build Tailwind CSS only                 | Manual CSS compilation        |
| `valksor:importmap`        | Update import maps only                 | Manual JavaScript updates     |
| `valksor:binary`           | Download build tools                    | Setup or update tools         |
| `valksor:icons`            | Generate SVG icons                      | When using icon system        |
| `valksor:hot-reload`       | Hot reload service only                 | Custom reload setups          |
| `valksor:binaries:install` | Install all binaries                    | Fresh environment setup       |

### Command Options

All commands support these useful options:

```bash
# Run on specific app (multi-app projects)
php bin/console valksor:watch --id=www

# Non-interactive mode (for CI/automated scripts)
php bin/console valksor:watch --non-interactive

# Specify environment
php bin/console valksor:dev --env=prod
```

## What's Included?

This meta-package automatically includes:

- **valksor/php-dev-build** - Build tools, hot reloading, and asset management
- **valksor/php-dev-cs-fixer-custom-fixers** - Enhanced code quality fixers

### Build Tools Features

- **Hot Reloading**: Automatic browser refresh on file changes
- **Tailwind CSS**: Integrated compilation with DaisyUI support
- **Import Maps**: Modern JavaScript dependency management
- **Binary Management**: Automatic tool downloads (ESBuild, Tailwind CSS)
- **Icon Generation**: SVG icon processing with Lucide integration

### Code Quality Features

- **PHP 8.4+ Best Practices**: Modern coding standards
- **Promoted Constructor Properties**: Automatic refactoring
- **Doctrine Migration Cleanup**: Remove auto-generated comments
- **Custom Fixers**: Additional rules beyond PSR-12

## Development Workflow

### Daily Development

```bash
# Start your day
php bin/console valksor:watch

# Work on your files - changes trigger automatic reloads
# Edit templates → browser reloads
# Edit CSS → Tailwind compiles → browser reloads
# Edit PHP → browser reloads
```

### Before Deployment

```bash
# Build optimized assets
php bin/console valksor-prod:build
```

## Multi-App Projects

For projects with multiple applications, you need to configure the project structure and use app IDs.

### Project Structure Setup

Organize your multi-app project like this:

```
your-project/
├── apps/
│   ├── www/                    # Main website
│   │   ├── assets/
│   │   │   ├── js/
│   │   │   └── styles/
│   │   └── templates/
│   ├── admin/                  # Admin interface
│   │   ├── assets/
│   │   └── templates/
│   └── api/                    # API application
│       └── templates/
├── infrastructure/              # Shared components
│   ├── src/
│   └── templates/
└── config/
    └── packages/
        └── valksor.yaml
```

### Multi-App Configuration

```yaml
# config/packages/valksor.yaml
valksor:
    build:
        enabled: true
        project:
            apps_dir: "apps" # Directory containing apps
            infrastructure_dir: "infrastructure" # Shared code
        hot_reload:
            enabled: true
        tailwind:
            enabled: true
        importmap:
            enabled: true
```

### Using App IDs

Each app directory name becomes an app ID you can use with commands:

```bash
# Run on all apps (default)
php bin/console valksor:watch

# Run on specific app only
php bin/console valksor:watch --id=www       # Watch only apps/www/
php bin/console valksor:watch --id=admin     # Watch only apps/admin/
php bin/console valksor:watch --id=api       # Watch only apps/api/

# Build assets for specific app
php bin/console valksor-prod:build --id=www

# Generate icons for specific app
php bin/console valksor:icons --id=admin
```

### Multi-App Frontend Integration

For multi-app projects, include the app ID in your templates:

```twig
{# apps/www/templates/base.html.twig #}
<!DOCTYPE html>
<html>
<head>
    <title>{% block title %}Website{% endblock %}</title>
    <link rel="stylesheet" href="{{ asset('www/styles/app.css') }}">
    {{ valksor_sse_importmap_definition(['infrastructure/head', 'www/head']) }}
    {{ valksor_sse_importmap_scripts(['infrastructure/head', 'www/head']) }}
</head>
<body>
{% block body %}{% endblock %}
{% block javascripts %}
    {{ valksor_sse_importmap_scripts(['infrastructure/app', 'www/app']) }}
    {{ include('@ValksorSse/sse.html.twig') }}
{% endblock %}
</body>
</html>
```

**Key points:**

- Use `{{ asset(app_id ~ '/styles/app.css') }}` for app-specific CSS
- Include app ID in import map arrays: `['infrastructure/head', 'www/head']`
- Each app gets its own hot reload and asset compilation

## Requirements

- **PHP 8.4 or higher**
- **inotify extension** (for file watching)
- **PCNTL extension** (for process management)
- **POSIX extension**
- **friendsofphp/php-cs-fixer** (3.81.0 or higher)
- **symfony/framework-bundle** (7.2.0 or higher)
- **Valksor Bundle** and related components

## Platform Requirements

⚠️ **Linux Only Required**

The Valksor PHP Dev toolkit requires **Linux** due to its dependency on the **inotify** extension for efficient file system monitoring.

- **inotify** is a Linux kernel subsystem available only on Linux platforms
- File watching is essential for the hot reload, asset compilation, and development workflow features
- These tools are not compatible with Windows or macOS
- Docker containers can provide a Linux environment on other platforms

## Project Structure

### Standard Single-App Setup

For most Symfony projects, use the standard structure:

```
your-project/
├── assets/
│   ├── js/
│   │   └── app.js
│   └── styles/
│       └── app.css
├── templates/
│   ├── base.html.twig
│   └── index.html.twig
├── src/
│   └── Controller/
├── public/
│   └── styles/          # Compiled CSS (auto-generated)
├── config/
│   └── packages/
│       └── valksor.yaml
└── bin/
    └── console
```

### Multi-App Setup

For complex projects with multiple applications:

```
your-project/
├── apps/
│   ├── www/
│   │   ├── assets/
│   │   └── templates/
│   └── admin/
│       ├── assets/
│       └── templates/
├── infrastructure/          # Shared code
│   ├── src/
│   └── templates/
├── config/
│   └── packages/
│       └── valksor.yaml
└── bin/
    └── console
```

### What Gets Auto-Discovered

Valksor Dev automatically finds and processes:

- **Tailwind CSS files**: `*.tailwind.css`, `assets/styles/*.css`
- **JavaScript files**: `assets/js/*.js`, `apps/*/assets/js/*.js`
- **Templates**: `templates/**/*.html.twig`, `apps/*/templates/**/*.html.twig`
- **Icons**: `assets/icons/*.svg`, `assets/icons/**/*.svg`

### First Run - What to Expect

When you first run `valksor:watch`:

1. **Binary Downloads**: ESBuild, Tailwind CSS, and other tools are downloaded automatically
2. **Asset Discovery**: Valksor scans your project for CSS, JS, and template files
3. **Initial Compilation**: CSS is compiled and import maps are generated
4. **Server Start**: Hot reload server starts on port 8080

You should see output like:

```
[Valksor] Downloading build tools...
[Valksor] Scanning for assets...
[Valksor] Starting hot reload server on port 8080
[Valksor] Watching for file changes...
```

### Common Setup Issues

**Permission errors on binary downloads:**

```bash
# Fix binary directory permissions
chmod +x bin/build-tools/*
```

**Inotify limits (Linux):**

```bash
# Check current limit
cat /proc/sys/fs/inotify/max_user_watches

# Increase limit if needed
echo 'fs.inotify.max_user_watches=524288' | sudo tee -a /etc/sysctl.conf
sudo sysctl -p
```

**Port 8080 already in use:**

```bash
# Check what's using the port
lsof -i :8080

# Kill existing processes
pkill -f valksor
```

## Documentation

- **[Build Tools Guide](src/ValksorDev/Build/README.md)** - Detailed configuration and workflow
- **[Custom Fixers Guide](src/ValksorDev/PhpCsFixerCustomFixers/README.md)** - All available code quality fixers

## License

This package is licensed under the [BSD-3-Clause License](LICENSE).

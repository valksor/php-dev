# ValksorDev Snapshot

[![valksor](https://badgen.net/static/org/valksor/green)](https://github.com/valksor)
[![BSD-3-Clause](https://img.shields.io/badge/BSD--3--Clause-green?style=flat)](https://github.com/valksor/php-dev-snapshot/blob/master/LICENSE)
[![Coverage Status](https://coveralls.io/repos/github/valksor/php-dev-snapshot/badge.svg?branch=master)](https://coveralls.io/github/valksor/php-dev-snapshot?branch=master)
[![php](https://badgen.net/static/php/>=8.4/purple)](https://www.php.net/releases/8.4/en.php)

A powerful PHP snapshot generation tool that creates AI-optimized Markdown Context Pack (MCP) documentation from project files. Designed for AI code analysis, project documentation generation, and knowledge base creation with intelligent file filtering and content processing.

## What It Does

The snapshot generator provides comprehensive project documentation with:

- **Intelligent File Filtering**: Automatically excludes dependencies, build artifacts, and irrelevant files
- **Binary File Detection**: Skips binary files and focuses on source code only
- **Content Limiting**: Configurable limits for file size, line count, and total files processed
- **MCP Format Output**: Generates AI-optimized markdown documentation with structured metadata
- **Gitignore Integration**: Respects existing exclusion patterns and supports custom rules
- **Multi-Path Support**: Can scan multiple directories and merge results

## Configuration

### Basic Setup

```yaml
# config/packages/valksor.yaml
valksor:
    snapshot:
        enabled: true
        max_files: 500
        max_lines: 1000
        max_file_size: 1024 # KB
```

### Complete Configuration Reference

```yaml
# config/packages/valksor.yaml
valksor:
    snapshot:
        enabled: true

        # File Processing Limits
        max_files: 500      # Maximum files to process
        max_lines: 1000     # Maximum lines per file
        max_file_size: 1024 # Maximum file size in KB

        # Exclusion Patterns (Gitignore-style)
        exclude:
            # Test and build directories (anywhere in project)
            - "tests"
            - "Tests"
            - "coverage"
            - ".coverage"
            - "build"
            - "dist"
            - "out"

            # Cache and temporary directories (anywhere in project)
            - ".phpunit.cache"
            - "cache"
            - "tmp"
            - "temp"

            # Large dependency directories (anywhere in project)
            - "node_modules"
            - "vendor"

            # File extensions (match files ending with extension)
            - ".neon"
            - ".md"
            - ".lock"

            # Root-only exclusions (only at project root)
            - "config/"
```

### Pattern Types Explained

#### Directory Patterns
- `vendor` - Excludes any directory named "vendor" anywhere in the project
- `node_modules` - Excludes any directory named "node_modules" anywhere
- `tests` - Excludes any directory named "tests" or "Tests" anywhere

#### File Extension Patterns
- `.neon` - Excludes all files ending with `.neon`
- `.md` - Excludes all markdown files
- `.lock` - Excludes lock files

#### Root-Only Patterns
- `config/` - Excludes `config/` directory only at project root (not `src/config/`)

#### Glob Patterns
- `**/coverage/**` - Excludes any directory named "coverage" and its contents
- `**/*.log` - Excludes all `.log` files anywhere in the project

## Usage

### Command Line Interface

```bash
# Generate snapshot of entire project
php bin/console valksor:snapshot

# Scan specific directory
php bin/console valksor:snapshot --path=src/

# Scan multiple directories
php bin/console valksor:snapshot --path=src/ --path=config/

# Custom output file
php bin/console valksor:snapshot --output_file=project_docs.mcp

# Custom limits
php bin/console valksor:snapshot --max_files=1000 --max_lines=2000

# Custom file size limit (in KB)
php bin/console valksor:snapshot --max_file_size=2048
```

### Command Options

| Option            | Description                                       | Default                          |
|-------------------|---------------------------------------------------|----------------------------------|
| `--path`          | Directory(s) to scan (can be used multiple times) | Project root                     |
| `--output_file`   | Output file path                                  | `snapshot_YYYY_MM_DD_HHMMSS.mcp` |
| `--max_files`     | Maximum files to process                          | 500                              |
| `--max_lines`     | Maximum lines per file                            | 1000                             |
| `--max_file_size` | Maximum file size in KB                           | 1024                             |

### Output Format

The snapshot generates an MCP (Markdown Context Pack) file with:

```markdown
# Project Name Snapshot

This is a comprehensive MCP snapshot of the Project Name project generated on...

## Summary
- **Files Processed:** 42
- **Total Size:** 156.7 KB
- **Generated:** 2024-01-15 14:30:22

## Files

### src/Controller/UserController.php
**Path:** `src/Controller/UserController.php`
**Lines:** 85
**Size:** 3,245 bytes

```php
<?php
// File content here
```

### config/services.yaml
**Path:** `config/services.yaml`
**Lines:** 23
**Size:** 892 bytes

```yaml
# File content here
```
```

## Advanced Usage

### Custom Exclusion Patterns

You can extend the default exclusion patterns with your own:

```yaml
valksor:
    snapshot:
        exclude:
            # Custom patterns for your project
            - "legacy/"           # Exclude legacy code directory
            - "**/test*.php"      # Exclude test PHP files
            - "docs/api/**"       # Exclude generated API docs
            - "*.generated.php"   # Exclude generated PHP files
            - "temp/**"           # Exclude temp directory contents
```

### Environment-Specific Snapshots

Different configurations for development vs production:

```yaml
# config/packages/dev/valksor.yaml
valksor:
    snapshot:
        enabled: true
        max_files: 1000      # More files for development
        max_lines: 2000      # More lines for debugging
        exclude:
            - "vendor/"
            - "node_modules/"
            - ".coverage"
```
```yaml
# config/packages/prod/valksor.yaml
valksor:
    snapshot:
        enabled: true
        max_files: 300       # Fewer files for production docs
        max_lines: 500       # Focused content
        exclude:
            - "vendor/"
            - "node_modules/"
            - "tests/"
            - "src/Dev/"     # Exclude dev utilities
```

### Integration with AI Workflows

The generated MCP files are optimized for AI consumption:

```bash
# Generate snapshot for AI code review
php bin/console valksor:snapshot --max_files=200 --output_file=ai_review.mcp

# Generate comprehensive documentation
php bin/console valksor:snapshot --max_files=1000 --max_lines=5000 --output_file=full_docs.mcp

# Generate focused API snapshot
php bin/console valksor:snapshot --path=src/Controller/ --path=src/Entity/ --output_file=api_docs.mcp
```

## File Processing Details

### Binary File Detection

The service automatically detects and excludes binary files by:

1. **Null Byte Detection**: Files containing `\x00` are skipped
2. **Extension Checking**: Known binary extensions are excluded by default
3. **Content Analysis**: Binary-like content patterns are filtered out

### Content Truncation

When files exceed the configured limits:

1. **Line Limiting**: Files are truncated at the specified line count
2. **Truncation Marker**: `# [Truncated at N lines]` is added
3. **Size Tracking**: Original file size is preserved in metadata

### Path Normalization

All paths are normalized relative to the project root:

- `/project/src/Controller.php` → `src/Controller.php`
- Relative paths are preserved as-is
- Trailing slashes are handled for directory patterns

## Performance Considerations

### Large Projects

For large codebases, consider these optimizations:

```yaml
valksor:
    snapshot:
        max_files: 100       # Start with fewer files
        max_lines: 500       # Reasonable line limits
        max_file_size: 512   # Smaller file size limit
        exclude:
            # Aggressive exclusions for speed
            - "vendor/"
            - "node_modules/"
            - "tests/"
            - ".coverage/"
            - "var/"
            - "public/"
```

### Incremental Snapshots

For frequent snapshots during development:

```bash
# Quick snapshot of recent changes
php bin/console valksor:snapshot --path=src/ --max_files=50 --max_lines=200

# Focused component documentation
php bin/console valksor:snapshot --path=src/Bundle/ --output_file=bundle_docs.mcp
```

## Troubleshooting

### Common Issues

**Memory Usage on Large Projects**
```yaml
valksor:
    snapshot:
        max_files: 200       # Reduce file count
        max_file_size: 512   # Reduce file size
```

**Missing Files**
```yaml
valksor:
    snapshot:
        exclude:
            # Remove overly broad exclusions
            # - "*.php"  # This would exclude all PHP files
            - "tests/"    # Use specific paths instead
```

**Empty Snapshots**
```bash
# Check if paths exist
php bin/console valksor:snapshot --path=nonexistent/path --verbose

# Verify exclusion patterns aren't too aggressive
php bin/console valksor:snapshot --verbose
```

## Best Practices

### Pattern Organization

Group exclusion patterns logically:

```yaml
valksor:
    snapshot:
        exclude:
            # Development artifacts
            - "tests/"
            - "coverage/"
            - ".coverage/"

            # Dependencies
            - "vendor/"
            - "node_modules/"

            # Build output
            - "public/"
            - "var/"
            - "dist/"

            # Documentation (exclude from code snapshot)
            - "*.md"
            - "docs/"

            # Configuration files
            - ".env*"
            - "*.lock"
```

### File Size Management

Set appropriate limits for your use case:

```yaml
# For AI code review
max_files: 100
max_lines: 1000
max_file_size: 1024
```
```yaml
# For comprehensive documentation
max_files: 1000
max_lines: 5000
max_file_size: 2048
```
```yaml
# For quick overviews
max_files: 50
max_lines: 500
max_file_size: 512
```

## Integration Examples

### CI/CD Integration

```yaml
# .github/workflows/docs.yml
name: Generate Documentation

on: [push]

jobs:
  snapshot:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - name: Install dependencies
        run: composer install
      - name: Generate snapshot
        run: |
          php bin/console valksor:snapshot \
            --output_file=project_snapshot.mcp \
            --max_files=500 \
            --max_lines=1000
      - name: Upload artifact
        uses: actions/upload-artifact@v2
        with:
          name: project-snapshot
          path: project_snapshot.mcp
```

### AI Code Review Workflow

```bash
#!/bin/bash
# scripts/ai-review.sh

# Generate focused snapshot for review
php bin/console valksor:snapshot \
  --path=src/ \
  --path=templates/ \
  --max_files=200 \
  --max_lines=2000 \
  --output_file=review_snapshot.mcp

# Send to AI service (example)
# ai-review --snapshot=review_snapshot.mcp --prompt="Review this code for security issues"
```

### Documentation Generation

```bash
#!/bin/bash
# scripts/generate-docs.sh

# Generate comprehensive API documentation
php bin/console valksor:snapshot \
  --path=src/Controller/ \
  --path=src/Entity/ \
  --path=config/ \
  --exclude="tests/" \
  --exclude="vendor/" \
  --output_file=api_documentation.mcp \
  --max_files=300 \
  --max_lines=3000

echo "API documentation generated: api_documentation.mcp"
```

## License

This package is licensed under the BSD-3-Clause License. See the [LICENSE](LICENSE) file for details.
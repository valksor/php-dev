# Sample Documentation for Snapshot Testing

This is a sample markdown file to test how the snapshot generator handles documentation content.

## Overview

The snapshot generator is designed to create AI-optimized documentation from project files. This includes:

- PHP source code files
- Configuration files (YAML, JSON, etc.)
- Documentation files (like this one)
- Template files and other text-based assets

## Key Features

### Intelligent Filtering

The system automatically excludes:
- Binary files and executables
- Large dependency directories (vendor, node_modules)
- Build artifacts and cache files
- Test coverage reports
- Temporary files and logs

### Content Processing

Files are processed with these safeguards:
- **Binary Detection**: Files containing null bytes are excluded
- **Size Limits**: Configurable maximum file size (default: 1MB)
- **Line Limits**: Content is truncated if too long (default: 1000 lines)
- **Path Normalization**: All paths are relative to project root

### Output Format

The generated snapshot follows MCP (Markdown Context Pack) format:
- Project summary with statistics
- Individual file sections with metadata
- Formatted code blocks with syntax hints
- Processing information and timestamps

## Usage Examples

### Basic Usage

```bash
# Generate snapshot of entire project
php bin/console valksor:snapshot

# Custom output file
php bin/console valksor:snapshot --output_file=my_docs.mcp

# Custom limits
php bin/console valksor:snapshot --max_files=100 --max_lines=2000
```

### Advanced Configuration

```yaml
valksor:
    snapshot:
        enabled: true
        max_files: 500
        max_lines: 1000
        max_file_size: 1024
        exclude:
            - "tests/"
            - "coverage/"
            - "*.log"
            - "vendor/"
```

## Integration Scenarios

### AI Code Review

Generate focused snapshots for code analysis:

```bash
php bin/console valksor:snapshot \
  --path=src/ \
  --max_files=200 \
  --output_file=ai_review.mcp
```

### Documentation Generation

Create comprehensive project documentation:

```bash
php bin/console valksor:snapshot \
  --max_files=1000 \
  --max_lines=5000 \
  --output_file=project_docs.mcp
```

### Knowledge Base Creation

Build knowledge bases for AI training:

```bash
php bin/console valksor:snapshot \
  --exclude="tests/" \
  --exclude="var/" \
  --max_file_size=2048 \
  --output_file=knowledge_base.mcp
```

## File Types Supported

The snapshot generator handles various file types:

### Code Files
- **PHP** (.php) - Source code with syntax highlighting
- **JavaScript** (.js, .mjs) - Client and server-side JavaScript
- **CSS** (.css) - Stylesheets and component styles
- **HTML** (.html, .htm) - Templates and markup
- **YAML** (.yaml, .yml) - Configuration files
- **JSON** (.json) - Data and configuration files

### Documentation Files
- **Markdown** (.md) - Documentation files like this one
- **Text** (.txt) - Plain text documentation
- **README** - Project documentation

### Configuration Files
- **Environment** (.env, .env.dist) - Environment configuration
- **XML** (.xml) - Configuration and data files
- **INI** (.ini) - Configuration files

## Best Practices

1. **Configure Appropriate Limits**: Set file and line limits based on your use case
2. **Use Exclusion Patterns**: Exclude irrelevant files to focus on important content
3. **Regular Snapshots**: Generate snapshots regularly to keep documentation current
4. **Version Control**: Include snapshots in your version control for tracking changes
5. **AI Integration**: Use snapshots as context for AI code review and analysis tools

## Troubleshooting

### Common Issues

**Empty Snapshots**
- Check if exclusion patterns are too broad
- Verify file paths are accessible
- Ensure file limits are reasonable

**Memory Issues**
- Reduce `max_files` limit
- Lower `max_file_size` setting
- Exclude large directories

**Missing Files**
- Review exclusion patterns
- Check file permissions
- Verify path specifications

### Debug Information

Use verbose mode for detailed processing information:

```bash
php bin/console valksor:snapshot --verbose
```

This will show:
- Files being processed
- Exclusion patterns matching
- File size and line information
- Processing statistics

## Security Considerations

When generating snapshots:

1. **Sensitive Data**: Ensure no sensitive information is included
2. **API Keys**: Exclude configuration files with secrets
3. **Credentials**: Use exclusion patterns for credential files
4. **Private Data**: Review snapshots before sharing externally

Typical exclusions for security:

```yaml
exclude:
    - ".env*"
    - "secrets/"
    - "private/"
    - "*.key"
    - "*.pem"
    - "config/secrets.yaml"
```

## Conclusion

The snapshot generator provides a powerful way to create AI-optimized documentation from your projects. By configuring appropriate limits and exclusion patterns, you can generate focused, useful documentation for various purposes including code review, knowledge base creation, and project documentation.
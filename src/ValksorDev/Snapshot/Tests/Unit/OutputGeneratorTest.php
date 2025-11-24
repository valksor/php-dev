<?php declare(strict_types = 1);

/*
 * This file is part of the Valksor package.
 *
 * (c) Davis Zalitis (k0d3r1s)
 * (c) SIA Valksor <packages@valksor.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ValksorDev\Snapshot\Tests\Unit;

use JsonException;
use PHPUnit\Framework\TestCase;
use ValksorDev\Snapshot\Util\OutputGenerator;

use function strlen;

/**
 * Tests for OutputGenerator class.
 *
 * Tests the MCP format output generation functionality.
 */
final class OutputGeneratorTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testGenerateFileSizeFormatting(): void
    {
        $files = [];
        $stats = [
            'files_processed' => 0,
            'total_size' => 2048, // 2 KB
        ];

        $output = OutputGenerator::generate('TestProject', $files, $stats);

        $this->assertStringContainsString('"total_size_kb": 2', $output);
        $this->assertStringContainsString('- **Total size**: 2 KB', $output);
    }

    /**
     * @throws JsonException
     */
    public function testGenerateWithEmptyFileList(): void
    {
        $files = [];
        $stats = [
            'files_processed' => 0,
            'total_size' => 0,
        ];

        $output = OutputGenerator::generate('TestProject', $files, $stats);

        $this->assertStringContainsString('# TestProject', $output);
        $this->assertStringContainsString('Project snapshot generated for AI analysis', $output);
        $this->assertStringContainsString('```mcp-metadata', $output);
        $this->assertStringContainsString('"num_files": 0', $output);
        $this->assertStringContainsString('## Project Structure', $output);
        $this->assertStringContainsString('## Summary', $output);
        $this->assertStringContainsString('- **Total files**: 0', $output);
    }

    /**
     * @throws JsonException
     */
    public function testGenerateWithFileBreakdownTable(): void
    {
        $files = [
            [
                'path' => '/project/src/test.php',
                'relative_path' => 'src/test.php',
                'content' => '<?php echo "test";',
                'size' => 18,
            ],
            [
                'path' => '/project/config.yaml',
                'relative_path' => 'config.yaml',
                'content' => 'key: value',
                'size' => 10,
            ],
        ];

        $stats = [
            'files_processed' => 2,
            'total_size' => 28,
        ];

        $output = OutputGenerator::generate('TestProject', $files, $stats);

        $this->assertStringContainsString('### File Breakdown', $output);
        $this->assertStringContainsString('| Language | Extension | Files | Size (KB) |', $output);
        $this->assertStringContainsString('| PHP | `php` | 1 | 0.02 |', $output);
        $this->assertStringContainsString('| YAML | `yaml` | 1 | 0.01 |', $output);
    }

    /**
     * @throws JsonException
     */
    public function testGenerateWithLargeFileSize(): void
    {
        $files = [];
        $stats = [
            'files_processed' => 0,
            'total_size' => 2048 * 1024, // 2 MB
        ];

        $output = OutputGenerator::generate('TestProject', $files, $stats);

        $this->assertStringContainsString('"total_size_kb": 2048', $output);
        $this->assertStringContainsString('- **Total size**: 2048 KB', $output);
    }

    /**
     * @throws JsonException
     */
    public function testGenerateWithLongContent(): void
    {
        $longContent = str_repeat("Line of content\n", 50); // 50 lines
        $files = [
            [
                'path' => '/project/src/long.php',
                'relative_path' => 'src/long.php',
                'content' => $longContent,
                'size' => strlen($longContent),
            ],
        ];

        $stats = [
            'files_processed' => 1,
            'total_size' => strlen($longContent),
        ];

        $output = OutputGenerator::generate('TestProject', $files, $stats);

        $this->assertStringContainsString('### PHP Files', $output);
        $this->assertStringContainsString('#### src/long.php', $output);
        $this->assertStringContainsString('```php', $output);
        $this->assertStringContainsString($longContent, $output);
        $this->assertStringContainsString('## Project Structure', $output);
        $this->assertStringContainsString('- **Total files**: 1', $output);
    }

    /**
     * @throws JsonException
     */
    public function testGenerateWithMultipleFiles(): void
    {
        $files = [
            [
                'path' => '/project/src/test.php',
                'relative_path' => 'src/test.php',
                'content' => '<?php echo "Hello";',
                'size' => 19,
            ],
            [
                'path' => '/project/README.md',
                'relative_path' => 'README.md',
                'content' => '# Test Project',
                'size' => 14,
            ],
        ];

        $stats = [
            'files_processed' => 2,
            'total_size' => 33,
        ];

        $output = OutputGenerator::generate('TestProject', $files, $stats);

        $this->assertStringContainsString('"num_files": 2', $output);
        $this->assertStringContainsString('## Files', $output);
        $this->assertStringContainsString('### PHP Files', $output);
        $this->assertStringContainsString('#### src/test.php', $output);
        $this->assertStringContainsString('### Markdown Files', $output);
        $this->assertStringContainsString('#### README.md', $output);
        $this->assertStringContainsString('```php', $output);
        $this->assertStringContainsString('```markdown', $output);
        $this->assertStringContainsString('<?php echo "Hello";', $output);
        $this->assertStringContainsString('# Test Project', $output);
        $this->assertStringContainsString('- **Total files**: 2', $output);
        $this->assertStringContainsString('src/', $output);
        $this->assertStringContainsString('README.md', $output);
    }

    /**
     * @throws JsonException
     */
    public function testGenerateWithSingleFile(): void
    {
        $files = [
            [
                'path' => '/project/src/test.php',
                'relative_path' => 'src/test.php',
                'content' => '<?php echo "Hello World";',
                'size' => 26,
            ],
        ];

        $stats = [
            'files_processed' => 1,
            'total_size' => 26,
        ];

        $output = OutputGenerator::generate('TestProject', $files, $stats);

        $this->assertStringContainsString('# TestProject', $output);
        $this->assertStringContainsString('```mcp-metadata', $output);
        $this->assertStringContainsString('"num_files": 1', $output);
        $this->assertStringContainsString('## Files', $output);
        $this->assertStringContainsString('### PHP Files', $output);
        $this->assertStringContainsString('#### src/test.php', $output);
        $this->assertStringContainsString('```php', $output);
        $this->assertStringContainsString('<?php echo "Hello World";', $output);
        $this->assertStringContainsString('## Project Structure', $output);
        $this->assertStringContainsString('src/', $output);
        $this->assertStringContainsString('src/test.php', $output);
        $this->assertStringContainsString('- **Total files**: 1', $output);
    }
}

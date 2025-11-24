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

namespace ValksorDev\Snapshot\Tests\Support;

use JsonException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use ValksorDev\Snapshot\Service\SnapshotService;

use function dirname;

/**
 * Helper class for snapshot testing utilities.
 *
 * Provides common functionality for setting up test environments
 * and creating test data for snapshot functionality tests.
 */
final class SnapshotTestHelper
{
    /**
     * Assert that snapshot output contains expected elements.
     *
     * @param string        $snapshotContent Generated snapshot content
     * @param array<string> $expectedFiles   Files that should be included
     * @param array<string> $excludedFiles   Files that should be excluded
     *
     * @return array<string> Missing files
     */
    public static function assertSnapshotContent(
        string $snapshotContent,
        array $expectedFiles = [],
        array $excludedFiles = [],
    ): array {
        $missing = [];

        foreach ($expectedFiles as $file) {
            if (!str_contains($snapshotContent, $file)) {
                $missing[] = $file;
            }
        }

        foreach ($excludedFiles as $file) {
            if (str_contains($snapshotContent, $file)) {
                $missing[] = "EXCLUDED_BUT_FOUND: $file";
            }
        }

        return $missing;
    }

    /**
     * Clean up a test directory.
     *
     * @param string $directory Directory to remove
     */
    public static function cleanupTestDirectory(
        string $directory,
    ): void {
        if (!is_dir($directory)) {
            return;
        }

        foreach (array_diff(scandir($directory), ['.', '..']) as $file) {
            $path = $directory . '/' . $file;

            if (is_dir($path)) {
                self::cleanupTestDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }

    /**
     * Create a SnapshotService instance for testing.
     *
     * @param string $projectDir Project directory path
     * @param array  $config     Configuration parameters
     *
     * @return SnapshotService Configured snapshot service
     */
    public static function createSnapshotService(
        string $projectDir,
        array $config = [],
    ): SnapshotService {
        $parameterBag = new ParameterBag(array_merge([
            'kernel.project_dir' => $projectDir,
            'valksor.snapshot.options' => [
                'enabled' => true,
                'max_files' => 100,
                'max_lines' => 500,
                'max_file_size' => 512,
                'exclude' => ['vendor/', 'tests/', '.coverage/'],
            ],
        ], $config));

        return new SnapshotService($parameterBag);
    }

    /**
     * Create a temporary file with specific content.
     *
     * @param string $content   File content
     * @param string $extension File extension
     *
     * @return string Path to created file
     */
    public static function createTempFile(
        string $content,
        string $extension = 'txt',
    ): string {
        $tempFile = tempnam(sys_get_temp_dir(), 'snapshot_test_') . '.' . $extension;

        // Rename to include extension
        $finalFile = substr($tempFile, 0, -4) . '.' . $extension;
        rename($tempFile, $finalFile);

        file_put_contents($finalFile, $content);

        return $finalFile;
    }

    /**
     * Create a temporary directory structure for testing.
     *
     * @param string        $baseDir Base directory for test files
     * @param array<string> $files   Files to create (relative to baseDir)
     *
     * @return string The created directory path
     *
     * @throws JsonException
     */
    public static function createTestDirectory(
        string $baseDir,
        array $files = [],
    ): string {
        $testDir = $baseDir . '/snapshot_test_' . uniqid('', true);
        mkdir($testDir, 0o755, true);

        foreach ($files as $file) {
            $filePath = $testDir . '/' . $file;
            $fileDir = dirname($filePath);

            if (!is_dir($fileDir)) {
                mkdir($fileDir, 0o755, true);
            }

            // Create sample content based on file extension
            $content = self::generateSampleContent($file);
            file_put_contents($filePath, $content);
        }

        return $testDir;
    }

    /**
     * Read fixture file content.
     *
     * @param string $filename Fixture filename
     *
     * @return string File content
     */
    public static function getFixtureContent(
        string $filename,
    ): string {
        return file_get_contents(self::getFixturePath($filename));
    }

    /**
     * Get fixture file path.
     *
     * @param string $filename Fixture filename
     *
     * @return string Full path to fixture file
     */
    public static function getFixturePath(
        string $filename,
    ): string {
        return __DIR__ . '/../Fixtures/' . $filename;
    }

    /**
     * Generate JSON content.
     *
     * @return string JSON content
     *
     * @throws JsonException
     */
    private static function generateJsonContent(): string
    {
        return json_encode([
            'name' => 'Test Project',
            'version' => '1.0.0',
            'settings' => [
                'enabled' => true,
                'debug' => false,
            ],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    /**
     * Generate Markdown content.
     *
     * @return string Markdown content
     */
    private static function generateMarkdownContent(): string
    {
        return "# Test Documentation\n\nThis is a test markdown file.\n\n## Features\n\n- Feature 1\n- Feature 2\n\n## Usage\n\n```bash\nphp bin/console test:command\n```\n";
    }

    /**
     * Generate PHP content.
     *
     * @param string $filename File name for class naming
     *
     * @return string PHP content
     */
    private static function generatePhpContent(
        string $filename,
    ): string {
        $className = ucfirst(pathinfo($filename, PATHINFO_FILENAME));

        return "<?php declare(strict_types = 1);\n\n" .
               "namespace TestProject;\n\n" .
               "/**\n" .
               " * $className class.\n" .
               " */\n" .
               "final class $className\n" .
               "{\n" .
               "    private string \$name;\n\n" .
               "    public function __construct(string \$name = 'default')\n" .
               "    {\n" .
               "        \$this->name = \$name;\n" .
               "    }\n\n" .
               "    public function getName(): string\n" .
               "    {\n" .
               "        return \$this->name;\n" .
               "    }\n" .
               "}\n";
    }

    /**
     * Generate sample content based on file type.
     *
     * @param string $filename File name/path
     *
     * @return string Generated content
     *
     * @throws JsonException
     */
    private static function generateSampleContent(
        string $filename,
    ): string {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'php' => self::generatePhpContent($filename),
            'yaml', 'yml' => self::generateYamlContent(),
            'md' => self::generateMarkdownContent(),
            'json' => self::generateJsonContent(),
            'txt' => self::generateTextContent(),
            default => "Sample content for $filename\nLine 2\nLine 3",
        };
    }

    /**
     * Generate plain text content.
     *
     * @return string Text content
     */
    private static function generateTextContent(): string
    {
        return "This is a test text file.\n\nIt contains multiple lines.\n\nUsed for testing snapshot generation.\n";
    }

    /**
     * Generate YAML content.
     *
     * @return string YAML content
     */
    private static function generateYamlContent(): string
    {
        return "# Test configuration\ntest_project:\n  name: Test Project\n  version: 1.0.0\n  settings:\n    enabled: true\n    debug: false\n";
    }
}

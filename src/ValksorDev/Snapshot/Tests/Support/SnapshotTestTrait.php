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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use ValksorDev\Snapshot\Service\SnapshotService;

/**
 * Trait providing common functionality for snapshot testing.
 *
 * This trait can be used by test classes to get access to common
 * setup, teardown, and assertion methods for snapshot functionality.
 */
trait SnapshotTestTrait
{
    /** @var array<string> List of temporary directories to cleanup */
    private array $tempDirs = [];

    /** @var array<string> List of temporary files to cleanup */
    private array $tempFiles = [];

    /**
     * Assert that a file contains expected content patterns.
     *
     * @param string        $filePath           Path to file to check
     * @param array<string> $expectedPatterns   Patterns that should be present
     * @param array<string> $unexpectedPatterns Patterns that should be absent
     */
    protected function assertFileContains(
        string $filePath,
        array $expectedPatterns = [],
        array $unexpectedPatterns = [],
    ): void {
        $this->assertFileExists($filePath);
        $content = file_get_contents($filePath);

        foreach ($expectedPatterns as $pattern) {
            $this->assertStringContainsString(
                $pattern,
                $content,
                "File should contain: $pattern",
            );
        }

        foreach ($unexpectedPatterns as $pattern) {
            $this->assertStringNotContainsString(
                $pattern,
                $content,
                "File should not contain: $pattern",
            );
        }
    }

    /**
     * Assert that snapshot contains expected files and excludes others.
     *
     * @param string        $snapshotContent Generated snapshot content
     * @param array<string> $expectedFiles   Files that should be present
     * @param array<string> $excludedFiles   Files that should be absent
     */
    protected function assertSnapshotContent(
        string $snapshotContent,
        array $expectedFiles = [],
        array $excludedFiles = [],
    ): void {
        $missing = SnapshotTestHelper::assertSnapshotContent($snapshotContent, $expectedFiles, $excludedFiles);

        $this->assertEmpty(
            $missing,
            'Snapshot content mismatch. Missing: ' . implode(', ', $missing),
        );
    }

    /**
     * Clean up all temporary files and directories.
     */
    protected function cleanupTempFiles(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        foreach ($this->tempDirs as $dir) {
            SnapshotTestHelper::cleanupTestDirectory($dir);
        }

        $this->tempFiles = [];
        $this->tempDirs = [];
    }

    /**
     * Create ParameterBag with test configuration.
     *
     * @param string $projectDir       Project directory
     * @param array  $additionalConfig Additional parameters
     *
     * @return ParameterBagInterface Configured parameter bag
     */
    protected function createParameterBag(
        string $projectDir,
        array $additionalConfig = [],
    ): ParameterBagInterface {
        $defaultConfig = [
            'kernel.project_dir' => $projectDir,
            'valksor.snapshot.options' => [
                'enabled' => true,
                'max_files' => 100,
                'max_lines' => 500,
                'max_file_size' => 512,
                'exclude' => ['vendor/', 'tests/', '.coverage/'],
            ],
        ];

        return new ParameterBag(array_merge($defaultConfig, $additionalConfig));
    }

    /**
     * Create sample Markdown content.
     *
     * @param string $title Document title
     *
     * @return string Markdown file content
     */
    protected function createSampleMarkdownContent(
        string $title = 'Test Documentation',
    ): string {
        return "# $title\n\n" .
               "This is sample documentation for testing.\n\n" .
               "## Features\n\n" .
               "- Feature 1: Automatic file scanning\n" .
               "- Feature 2: Binary file detection\n" .
               "- Feature 3: Content truncation\n" .
               "- Feature 4: Pattern-based exclusion\n\n" .
               "## Configuration\n\n" .
               "```yaml\n" .
               "test:\n" .
               "  enabled: true\n" .
               "  max_files: 100\n" .
               "```\n\n" .
               "## Usage\n\n" .
               "1. Create configuration file\n" .
               "2. Run snapshot command\n" .
               "3. Review generated output\n\n" .
               "## Example Output\n\n" .
               "```php\n" .
               "// Sample PHP code\n" .
               "echo 'Hello, World!';\n" .
               "```\n";
    }

    /**
     * Create sample PHP file content.
     *
     * @param string $className Name for the PHP class
     *
     * @return string PHP file content
     */
    protected function createSamplePhpContent(
        string $className,
    ): string {
        return "<?php declare(strict_types = 1);\n\n" .
               "namespace TestProject;\n\n" .
               "/**\n" .
               " * $className class for testing.\n" .
               " */\n" .
               "final class $className\n" .
               "{\n" .
               "    private string \$name;\n" .
               "    private array \$items = [];\n\n" .
               "    public function __construct(string \$name = 'default')\n" .
               "    {\n" .
               "        \$this->name = \$name;\n" .
               "    }\n\n" .
               "    public function getName(): string\n" .
               "    {\n" .
               "        return \$this->name;\n" .
               "    }\n\n" .
               "    public function addItem(string \$item): void\n" .
               "    {\n" .
               "        \$this->items[] = \$item;\n" .
               "    }\n\n" .
               "    public function getItems(): array\n" .
               "    {\n" .
               "        return \$this->items;\n" .
               "    }\n" .
               "}\n";
    }

    /**
     * Create sample YAML content.
     *
     * @param string $projectName Name for the project
     *
     * @return string YAML file content
     */
    protected function createSampleYamlContent(
        string $projectName = 'TestProject',
    ): string {
        return "# Configuration for $projectName\n" .
               "$projectName:\n" .
               "  name: '$projectName'\n" .
               "  version: '1.0.0'\n" .
               "  settings:\n" .
               "    enabled: true\n" .
               "    debug: false\n" .
               "    max_items: 100\n" .
               "  paths:\n" .
               "    src: 'src/'\n" .
               "    config: 'config/'\n" .
               "    templates: 'templates/'\n" .
               "  exclude:\n" .
               "    - 'vendor/'\n" .
               "    - 'tests/'\n" .
               "    - '.coverage/'\n";
    }

    /**
     * Create a SnapshotService for testing with custom configuration.
     *
     * @param string|null $projectDir Project directory (creates temp if null)
     * @param array       $config     Additional configuration
     *
     * @return SnapshotService Configured service instance
     *
     * @throws JsonException
     */
    protected function createSnapshotService(
        ?string $projectDir = null,
        array $config = [],
    ): SnapshotService {
        if (null === $projectDir) {
            $projectDir = $this->createTestDirectory();
        }

        return SnapshotTestHelper::createSnapshotService($projectDir, $config);
    }

    /**
     * Create a temporary file with content.
     *
     * @param string $content   File content
     * @param string $extension File extension
     *
     * @return string Path to created file
     */
    protected function createTempFile(
        string $content,
        string $extension = 'txt',
    ): string {
        $file = SnapshotTestHelper::createTempFile($content, $extension);
        $this->tempFiles[] = $file;

        return $file;
    }

    /**
     * Set up a test directory with specified files.
     *
     * @param array<string> $files Files to create in the test directory
     *
     * @return string Path to the created test directory
     *
     * @throws JsonException
     */
    protected function createTestDirectory(
        array $files = [],
    ): string {
        $baseDir = sys_get_temp_dir();
        $testDir = SnapshotTestHelper::createTestDirectory($baseDir, $files);
        $this->tempDirs[] = $testDir;

        return $testDir;
    }

    /**
     * Get content from fixtures directory.
     *
     * @param string $filename Fixture filename
     *
     * @return string File content
     */
    protected function getFixtureContent(
        string $filename,
    ): string {
        return SnapshotTestHelper::getFixtureContent($filename);
    }

    /**
     * Get full path to fixture file.
     *
     * @param string $filename Fixture filename
     *
     * @return string Full path to fixture
     */
    protected function getFixturePath(
        string $filename,
    ): string {
        return SnapshotTestHelper::getFixturePath($filename);
    }

    /**
     * Clean up after tests.
     *
     * This should be called in tearDown() methods of test classes using this trait.
     */
    protected function snapshotTearDown(): void
    {
        $this->cleanupTempFiles();
    }
}

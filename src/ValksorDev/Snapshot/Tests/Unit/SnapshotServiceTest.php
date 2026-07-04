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

use PHPUnit\Framework\TestCase;
use ValksorDev\Snapshot\Tests\Support\SnapshotTestTrait;

use function file_get_contents;
use function file_put_contents;
use function str_repeat;
use function substr_count;

/**
 * Tests for the SnapshotService scanning engine.
 *
 * Exercises the real file-scanning behaviour: directory pruning, the size /
 * count / line limits, configuration-vs-CLI precedence and comment stripping.
 */
final class SnapshotServiceTest extends TestCase
{
    use SnapshotTestTrait;

    public function testAnchoredGlobExcludeDoesNotPruneNestedDirectories(): void
    {
        // 'config/**' must exclude only a top-level config/, never a nested one.
        $dir = $this->createTestDirectory(['config/top.php', 'app/config/nested.php']);
        $output = $this->outputPath();

        $result = $this->createSnapshotService($dir, [
            'valksor.snapshot.options' => [
                'enabled' => true,
                'max_files' => 100,
                'max_lines' => 500,
                'max_file_size' => 1048576,
                'exclude' => ['config/**'],
            ],
        ])->start([
            'paths' => [$dir],
            'output_file' => $output,
        ]);

        self::assertSame(0, $result);
        $this->assertFileContains($output, ['app/config/nested.php'], ['config/top.php']);
    }

    public function testCliMaxFilesOverridesConfiguredValue(): void
    {
        // Configured limit is 100, but the CLI passes 1 → only one file kept.
        $dir = $this->createTestDirectory(['a.php', 'b.php', 'c.php']);
        $output = $this->outputPath();

        $result = $this->createSnapshotService($dir)->start([
            'paths' => [$dir],
            'output_file' => $output,
            'max_files' => 1,
        ]);

        self::assertSame(0, $result);
        self::assertSame(1, substr_count(file_get_contents($output), '#### '));
    }

    public function testGeneratesSnapshotContainingScannedFiles(): void
    {
        $dir = $this->createTestDirectory(['src/Foo.php', 'config/app.yaml']);
        $output = $this->outputPath();

        $result = $this->createSnapshotService($dir)->start([
            'paths' => [$dir],
            'output_file' => $output,
        ]);

        self::assertSame(0, $result);
        $this->assertFileContains($output, ['src/Foo.php', 'config/app.yaml']);
    }

    public function testIgnoredDirectoriesArePrunedWithoutDroppingSiblings(): void
    {
        $dir = $this->createTestDirectory(['vendor/Skipped.php', 'src/Kept.php', 'src/AlsoKept.php']);
        $output = $this->outputPath();

        $result = $this->createSnapshotService($dir)->start([
            'paths' => [$dir],
            'output_file' => $output,
        ]);

        self::assertSame(0, $result);
        $this->assertFileContains(
            $output,
            ['src/Kept.php', 'src/AlsoKept.php'],
            ['vendor/Skipped.php'],
        );
    }

    public function testMaxFileSizeExcludesLargeFiles(): void
    {
        $dir = $this->createTestDirectory();
        file_put_contents($dir . '/small.txt', 'tiny');
        file_put_contents($dir . '/big.txt', str_repeat('x', 5000));
        $output = $this->outputPath();

        $result = $this->createSnapshotService($dir)->start([
            'paths' => [$dir],
            'output_file' => $output,
            'max_file_size' => 1000,
        ]);

        self::assertSame(0, $result);
        $this->assertFileContains($output, ['small.txt'], ['big.txt']);
    }

    public function testMaxLinesTruncatesContent(): void
    {
        $dir = $this->createTestDirectory();
        file_put_contents($dir . '/long.txt', str_repeat("line\n", 50));
        $output = $this->outputPath();

        $result = $this->createSnapshotService($dir)->start([
            'paths' => [$dir],
            'output_file' => $output,
            'max_lines' => 10,
        ]);

        self::assertSame(0, $result);
        self::assertStringContainsString('# [Truncated at 10 lines]', file_get_contents($output));
    }

    public function testReturnsZeroWhenNoFilesFound(): void
    {
        $dir = $this->createTestDirectory();
        $output = $this->outputPath();

        $result = $this->createSnapshotService($dir)->start([
            'paths' => [$dir],
            'output_file' => $output,
        ]);

        self::assertSame(0, $result);
    }

    public function testStripCommentsRemovesPhpComments(): void
    {
        $dir = $this->createTestDirectory();
        file_put_contents($dir . '/commented.php', "<?php\n// secret comment\n\$x = 1;\n");
        $output = $this->outputPath();

        $result = $this->createSnapshotService($dir)->start([
            'paths' => [$dir],
            'output_file' => $output,
            'strip_comments' => true,
        ]);

        self::assertSame(0, $result);
        $content = file_get_contents($output);
        self::assertStringNotContainsString('secret comment', $content);
        self::assertStringContainsString('$x = 1;', $content);
    }

    protected function tearDown(): void
    {
        $this->snapshotTearDown();
    }

    private function outputPath(): string
    {
        // A writable path outside the scanned directory.
        return $this->createTempFile('', 'mcp');
    }
}

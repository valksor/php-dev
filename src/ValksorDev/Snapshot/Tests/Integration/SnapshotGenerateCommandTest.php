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

namespace ValksorDev\Snapshot\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use ValksorDev\Snapshot\Command\SnapshotGenerateCommand;
use ValksorDev\Snapshot\Service\SnapshotService;

use function array_diff;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function rmdir;
use function scandir;
use function substr_count;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Integration tests for SnapshotGenerateCommand.
 *
 * Drives the command through CommandTester against a temporary project and
 * asserts deterministically on the generated MCP output.
 */
final class SnapshotGenerateCommandTest extends TestCase
{
    private SnapshotGenerateCommand $command;
    private CommandTester $commandTester;
    private string $outputFile;
    private ParameterBagInterface $parameterBag;
    private string $projectDir;

    public function testCommandExists(): void
    {
        self::assertSame('valksor:snapshot', $this->command->getName());
        self::assertStringContainsString('Generate project snapshots', $this->command->getDescription());
    }

    public function testGeneratesSnapshotWithExpectedSections(): void
    {
        file_put_contents($this->projectDir . '/test.php', "<?php\n\necho 'Hello World';\n");

        $result = $this->commandTester->execute([
            'paths' => [$this->projectDir],
            '--output' => $this->outputFile,
        ]);

        self::assertSame(Command::SUCCESS, $result);
        self::assertStringContainsString('Snapshot generated:', $this->commandTester->getDisplay());
        self::assertFileExists($this->outputFile);

        $content = file_get_contents($this->outputFile);
        self::assertStringContainsString('mcp-metadata', $content);
        self::assertStringContainsString('## Files', $content);
        self::assertStringContainsString('## Summary', $content);
        self::assertGreaterThanOrEqual(1, substr_count($content, '#### '));
    }

    public function testMaxFilesLimitIsApplied(): void
    {
        file_put_contents($this->projectDir . '/a.php', "<?php\n\$a = 1;\n");
        file_put_contents($this->projectDir . '/b.php', "<?php\n\$b = 2;\n");
        file_put_contents($this->projectDir . '/c.php', "<?php\n\$c = 3;\n");

        $result = $this->commandTester->execute([
            'paths' => [$this->projectDir],
            '--output' => $this->outputFile,
            '--max-files' => '1',
        ]);

        self::assertSame(Command::SUCCESS, $result);
        self::assertSame(1, substr_count(file_get_contents($this->outputFile), '#### '));
    }

    public function testWarnsWhenNoFilesFound(): void
    {
        $result = $this->commandTester->execute([
            'paths' => [$this->projectDir],
            '--output' => $this->outputFile,
        ]);

        self::assertSame(Command::SUCCESS, $result);
        self::assertStringContainsString('No files found', $this->commandTester->getDisplay());
    }

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/snapshot_test_' . uniqid('', true);
        mkdir($this->projectDir, 0o755, true);
        $this->outputFile = sys_get_temp_dir() . '/snapshot_out_' . uniqid('', true) . '.mcp';

        $this->parameterBag = new ParameterBag([
            'kernel.project_dir' => $this->projectDir,
            'valksor.snapshot.options' => [
                'enabled' => true,
                'max_files' => 100,
                'max_lines' => 500,
                'max_file_size' => 1048576,
                'exclude' => ['vendor/', '.git/'],
            ],
        ]);

        $this->command = new SnapshotGenerateCommand($this->parameterBag, new SnapshotService($this->parameterBag));
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->projectDir)) {
            $this->removeDirectory($this->projectDir);
        }

        if (is_file($this->outputFile)) {
            unlink($this->outputFile);
        }
    }

    private function removeDirectory(
        string $dir,
    ): void {
        if (!is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}

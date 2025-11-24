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

/**
 * Integration tests for SnapshotGenerateCommand class.
 *
 * Tests the command-line interface for snapshot generation.
 */
final class SnapshotGenerateCommandTest extends TestCase
{
    private SnapshotGenerateCommand $command;
    private CommandTester $commandTester;
    private ParameterBagInterface $parameterBag;

    public function testCommandExists(): void
    {
        $this->assertSame('valksor:snapshot', $this->command->getName());
        $this->assertStringContainsString('Generate project snapshots', $this->command->getDescription());
    }

    public function testCommandWithArguments(): void
    {
        $testDir = $this->parameterBag->get('kernel.project_dir');
        mkdir($testDir, 0o755, true);

        $testFile = $testDir . '/test.php';
        file_put_contents($testFile, '<?php echo "Hello World";');

        $result = $this->commandTester->execute([
            'paths' => [$testDir],
        ]);

        $this->assertSame(Command::SUCCESS, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Snapshot generated:', $output);
    }

    public function testCommandWithMaxOptions(): void
    {
        $testDir = $this->parameterBag->get('kernel.project_dir');
        mkdir($testDir, 0o755, true);

        $testFile = $testDir . '/test.php';
        file_put_contents($testFile, '<?php echo "Hello World";');

        $result = $this->commandTester->execute([
            '--max-files' => 1,
            '--max-size' => 1,
            '--max-lines' => 10,
        ]);

        $this->assertSame(Command::SUCCESS, $result);
        $output = $this->commandTester->getDisplay();
        // Command should succeed with options applied
        $this->assertTrue(
            str_contains($output, 'Snapshot generated:') || str_contains($output, 'No files found'),
            'Output should contain either success message or warning about no files',
        );
    }

    public function testCommandWithOutputOption(): void
    {
        $testDir = $this->parameterBag->get('kernel.project_dir');
        mkdir($testDir, 0o755, true);

        $testFile = $testDir . '/test.php';
        file_put_contents($testFile, '<?php echo "Hello World";');

        $outputFile = sys_get_temp_dir() . '/custom_snapshot_' . uniqid('', true) . '.mcp';

        $result = $this->commandTester->execute([
            '--output' => $outputFile,
        ]);

        $this->assertSame(Command::SUCCESS, $result);
        $output = $this->commandTester->getDisplay();
        // Command should succeed and either generate file or warn about no files
        $this->assertTrue(
            file_exists($outputFile) || str_contains($output, 'No files found'),
            'Either output file should exist or command should warn about no files',
        );

        if (file_exists($outputFile)) {
            unlink($outputFile);
        }
    }

    public function testCommandWithoutArguments(): void
    {
        // Create a temporary test directory with some files
        $testDir = $this->parameterBag->get('kernel.project_dir');
        mkdir($testDir, 0o755, true);

        $testFile = $testDir . '/test.php';
        file_put_contents($testFile, '<?php echo "Hello World";');

        $result = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $result);
        $output = $this->commandTester->getDisplay();
        // Command should succeed - either find files or warn about no files
        $this->assertTrue(
            str_contains($output, 'Snapshot generated:') || str_contains($output, 'No files found'),
            'Output should contain either success message or warning about no files',
        );
    }

    protected function setUp(): void
    {
        $this->parameterBag = new ParameterBag([
            'kernel.project_dir' => sys_get_temp_dir() . '/snapshot_test_' . uniqid('', true),
            'valksor.snapshot.options' => [
                'enabled' => true,
                'max_files' => 10,
                'max_lines' => 50,
                'max_file_size' => 100,
                'exclude' => ['vendor/', '.git/'],
            ],
        ]);

        $this->command = new SnapshotGenerateCommand($this->parameterBag, new SnapshotService($this->parameterBag));
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->parameterBag->get('kernel.project_dir'))) {
            $this->removeDirectory($this->parameterBag->get('kernel.project_dir'));
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

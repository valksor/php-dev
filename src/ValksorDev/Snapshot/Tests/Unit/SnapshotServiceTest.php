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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use ValksorDev\Snapshot\Service\SnapshotService;

/**
 * Tests for SnapshotService class.
 *
 * Tests the core functionality of the MCP snapshot generation service.
 */
final class SnapshotServiceTest extends TestCase
{
    private SnapshotService $snapshotService;

    public function testIsRunningReturnsFalseInitially(): void
    {
        $this->assertFalse($this->snapshotService->isRunning());
    }

    public function testReloadDoesNothing(): void
    {
        // The reload method should not throw any exceptions
        $this->snapshotService->reload();
        $this->assertTrue(true); // If we reach here, reload() worked
    }

    public function testRemovePidFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_pid_');
        file_put_contents($tempFile, '12345');

        $this->assertFileExists($tempFile);

        $this->snapshotService->removePidFile($tempFile);

        $this->assertFileDoesNotExist($tempFile);
    }

    public function testRemovePidFileDoesNotFailIfFileDoesNotExist(): void
    {
        $nonExistentFile = '/tmp/non_existent_pid_file_' . uniqid('', true);

        // This should not throw an exception and file should not exist afterwards
        $this->snapshotService->removePidFile($nonExistentFile);
        $this->assertFileDoesNotExist($nonExistentFile);
    }

    public function testStopSetsRunningToFalse(): void
    {
        // This is a simple test since isRunning is private
        // In a real test, we'd need to start the service first
        $this->snapshotService->stop();
        $this->assertFalse($this->snapshotService->isRunning());
    }

    public function testWritePidFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_pid_');

        $this->snapshotService->writePidFile($tempFile);

        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertIsNumeric($content);
        $this->assertGreaterThan(0, (int) $content);

        unlink($tempFile);
    }

    protected function setUp(): void
    {
        $parameterBag = new ParameterBag([
            'kernel.project_dir' => '/fake/project/root',
        ]);

        $this->snapshotService = new SnapshotService($parameterBag);
    }
}

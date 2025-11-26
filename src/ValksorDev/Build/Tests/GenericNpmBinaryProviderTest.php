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

namespace ValksorDev\Build\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use ValksorDev\Build\Binary\BinaryAssetManager;
use ValksorDev\Build\Binary\BinaryInterface;
use ValksorDev\Build\Binary\GenericNpmBinaryProvider;

use function array_diff;
use function file_put_contents;
use function is_dir;
use function json_encode;
use function mkdir;
use function scandir;
use function sys_get_temp_dir;
use function tempnam;
use function time;
use function unlink;

/**
 * @covers \ValksorDev\Build\Binary\GenericNpmBinaryProvider
 */
final class GenericNpmBinaryProviderTest extends TestCase
{
    private ParameterBagInterface $parameterBag;
    private string $tempDir;

    public function testCreateManager(): void
    {
        $provider = new GenericNpmBinaryProvider(
            $this->parameterBag,
            '@valksor/valksor,@valksor/ui',
        );

        $manager = $provider->createManager($this->tempDir . '/var');

        // Test that manager is created correctly without calling ensureLatest() to avoid network calls
        self::assertNotNull($manager);
        self::assertInstanceOf(BinaryAssetManager::class, $manager);
    }

    public function testCreateManagerWithRequestedName(): void
    {
        $provider = new GenericNpmBinaryProvider(
            $this->parameterBag,
            '@valksor/valksor,@valksor/ui',
        );

        $manager = $provider->createManager($this->tempDir . '/var', '@valksor/ui');

        // Test that manager is created correctly without calling ensureLatest() to avoid network calls
        self::assertNotNull($manager);
        self::assertInstanceOf(BinaryAssetManager::class, $manager);
    }

    public function testEnsureAll(): void
    {
        $provider = new GenericNpmBinaryProvider(
            $this->parameterBag,
            '@valksor/valksor,@valksor/ui',
        );

        $versions = $provider->ensureAll();

        self::assertCount(2, $versions);
        // Should return the fake versions we created in setUp
        self::assertSame(['1.0.0', '2.0.0'], $versions);
    }

    public function testImplementsBinaryInterface(): void
    {
        $provider = new GenericNpmBinaryProvider(
            $this->parameterBag,
            '@valksor/valksor',
        );

        self::assertInstanceOf(BinaryInterface::class, $provider);
    }

    public function testMultiplePackages(): void
    {
        $provider = new GenericNpmBinaryProvider(
            $this->parameterBag,
            '@valksor/valksor,@valksor/ui,@valksor/icons',
        );

        self::assertSame('generic_npm', $provider->getName());
        self::assertSame([
            '@valksor/valksor',
            '@valksor/ui',
            '@valksor/icons',
        ], $provider->getPackages());
        self::assertSame(3, $provider->getPackageCount());
        self::assertTrue($provider->hasPackage('@valksor/valksor'));
        self::assertTrue($provider->hasPackage('@valksor/ui'));
        self::assertTrue($provider->hasPackage('@valksor/icons'));
    }

    public function testPackageDirConversion(): void
    {
        $provider = new GenericNpmBinaryProvider(
            $this->parameterBag,
            '@valksor/valksor,@valksor/ui,@some/package',
        );

        $manager1 = $provider->createManager($this->tempDir . '/var');
        $manager2 = $provider->createManager($this->tempDir . '/var', '@valksor/ui');

        // Should create managers for the correct directories without calling ensureLatest()
        self::assertNotNull($manager1);
        self::assertNotNull($manager2);
        self::assertInstanceOf(BinaryAssetManager::class, $manager1);
        self::assertInstanceOf(BinaryAssetManager::class, $manager2);
    }

    public function testSinglePackage(): void
    {
        $provider = new GenericNpmBinaryProvider(
            $this->parameterBag,
            '@valksor/valksor',
        );

        self::assertSame('generic_npm', $provider->getName());
        self::assertSame(['@valksor/valksor'], $provider->getPackages());
        self::assertSame(1, $provider->getPackageCount());
        self::assertTrue($provider->hasPackage('@valksor/valksor'));
        self::assertFalse($provider->hasPackage('@valksor/ui'));
    }

    public function testWhitespaceHandling(): void
    {
        $provider = new GenericNpmBinaryProvider(
            $this->parameterBag,
            ' @valksor/valksor , @valksor/ui , @valksor/icons ',
        );

        self::assertSame([
            '@valksor/valksor',
            '@valksor/ui',
            '@valksor/icons',
        ], $provider->getPackages());
    }

    protected function setUp(): void
    {
        // Create a temporary directory for tests
        $this->tempDir = tempnam(sys_get_temp_dir(), 'generic_npm_test_');
        unlink($this->tempDir); // Remove file so we can create directory
        mkdir($this->tempDir, 0o755, true);

        $this->parameterBag = new ParameterBag([
            'kernel.project_dir' => $this->tempDir,
        ]);

        // Create fake package directories with version.json and package.json
        $this->createFakePackage('@valksor/valksor', '1.0.0');
        $this->createFakePackage('@valksor/ui', '2.0.0');
        $this->createFakePackage('@valksor/icons', '3.0.0');
        $this->createFakePackage('some/package', '4.0.0');
    }

    protected function tearDown(): void
    {
        // Cleanup temporary directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function createFakePackage(
        string $package,
        string $version,
    ): void {
        // Convert package name to directory name using the same logic as getPackageDir
        $packageDirName = str_replace('@', '', str_replace('/', '-', $package));
        $packageDir = $this->tempDir . '/var/' . $packageDirName;
        mkdir($packageDir, 0o755, true);

        // Create fake version.json
        file_put_contents($packageDir . '/version.json', json_encode([
            'version' => $version,
            'timestamp' => time(),
        ]));

        // Create fake package.json
        file_put_contents($packageDir . '/package.json', json_encode([
            'name' => $package,
            'version' => $version,
        ]));

        // Also create the directory structure that createManager tests expect
        $testDir = $this->tempDir . '/var/test/' . $packageDirName;
        mkdir($testDir, 0o755, true);
        file_put_contents($testDir . '/version.json', json_encode([
            'version' => $version,
            'timestamp' => time(),
        ]));
        file_put_contents($testDir . '/package.json', json_encode([
            'name' => $package,
            'version' => $version,
        ]));
    }

    private function removeDirectory(
        string $dir,
    ): void {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
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

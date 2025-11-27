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

use JsonException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use ValksorDev\Build\Binary\BinaryAssetManager;
use ValksorDev\Build\Binary\GenericNpmBinaryProvider;

use function array_diff;
use function file_put_contents;
use function in_array;
use function is_dir;
use function json_encode;
use function mkdir;
use function scandir;
use function strlen;
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

    /**
     * @throws JsonException
     */
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

    /**
     * @throws JsonException
     */
    protected function setUp(): void
    {
        // Set up HTTP mock first to intercept any requests
        $this->setUpHttpMock();

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
        // Restore original protocol handlers
        $this->tearDownHttpMock();

        // Cleanup temporary directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    /**
     * @throws JsonException
     */
    private function createFakePackage(
        string $package,
        string $version,
    ): void {
        // Convert package name to directory name using the same logic as getPackageDir
        $packageDirName = str_replace(['/', '@'], ['-', ''], $package);
        $packageDir = $this->tempDir . '/var/' . $packageDirName;
        mkdir($packageDir, 0o755, true);

        // Create fake version.json
        file_put_contents($packageDir . '/version.json', json_encode([
            'version' => $version,
            'timestamp' => time(),
        ], JSON_THROW_ON_ERROR));

        // Create fake package.json
        file_put_contents($packageDir . '/package.json', json_encode([
            'name' => $package,
            'version' => $version,
        ], JSON_THROW_ON_ERROR));

        // Also create the directory structure that createManager tests expect
        $testDir = $this->tempDir . '/var/test/' . $packageDirName;
        mkdir($testDir, 0o755, true);
        file_put_contents($testDir . '/version.json', json_encode([
            'version' => $version,
            'timestamp' => time(),
        ], JSON_THROW_ON_ERROR));
        file_put_contents($testDir . '/package.json', json_encode([
            'name' => $package,
            'version' => $version,
        ], JSON_THROW_ON_ERROR));
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

    /**
     * @throws JsonException
     */
    private function setUpHttpMock(): void
    {
        // Configure mock responses for npm registry
        MockHttpStreamWrapper::setMockResponses([
            'https://registry.npmjs.org/@valksor/valksor/latest' => json_encode([
                'version' => '1.0.0',
                'name' => '@valksor/valksor',
            ], JSON_THROW_ON_ERROR),
            'https://registry.npmjs.org/@valksor/ui/latest' => json_encode([
                'version' => '2.0.0',
                'name' => '@valksor/ui',
            ], JSON_THROW_ON_ERROR),
            'https://registry.npmjs.org/@valksor/icons/latest' => json_encode([
                'version' => '3.0.0',
                'name' => '@valksor/icons',
            ], JSON_THROW_ON_ERROR),
            'https://registry.npmjs.org/some/package/latest' => json_encode([
                'version' => '4.0.0',
                'name' => 'some/package',
            ], JSON_THROW_ON_ERROR),
        ]);

        // Register mock stream wrapper
        if (in_array('https', stream_get_wrappers(), true)) {
            stream_wrapper_unregister('https');
        }
        stream_wrapper_register('https', MockHttpStreamWrapper::class);
    }

    private function tearDownHttpMock(): void
    {
        // Restore original https wrapper
        if (in_array('https', stream_get_wrappers(), true)) {
            stream_wrapper_unregister('https');
        }
        stream_wrapper_restore('https');
    }
}

/**
 * Mock stream wrapper to intercept HTTP requests to npm registry during testing.
 */
class MockHttpStreamWrapper
{
    private string $data = '';

    private string $url = '';

    /** @var array<string, string> */
    private static array $mockResponses = [];

    public function stream_eof(): bool
    {
        return '' === $this->data;
    }

    public function stream_open(
        string $path,
    ): bool {
        $this->url = $path;

        // Return mock response if available, otherwise empty string
        $this->data = self::$mockResponses[$path] ?? '';

        return true;
    }

    public function stream_read(
        int $count,
    ): string {
        $result = substr($this->data, 0, $count);
        $this->data = substr($this->data, $count);

        return $result;
    }

    public function stream_stat(): array
    {
        return [
            'mode' => 0,
            'size' => strlen(self::$mockResponses[$this->url] ?? ''),
        ];
    }

    public function url_stat(
        string $path,
    ): array|false {
        if (isset(self::$mockResponses[$path])) {
            return [
                'mode' => 0,
                'size' => strlen(self::$mockResponses[$path]),
            ];
        }

        return false;
    }

    public static function setMockResponses(
        array $responses,
    ): void {
        self::$mockResponses = $responses;
    }
}

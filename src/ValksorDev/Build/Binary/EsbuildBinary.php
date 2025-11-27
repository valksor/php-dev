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

namespace ValksorDev\Build\Binary;

/**
 * Provider for esbuild binary using official download script.
 */
final class EsbuildBinary implements BinaryInterface
{
    public function createManager(
        string $varDir,
        ?string $requestedName = null,
    ): BinaryAssetManager {
        return self::createForEsbuild($varDir . '/esbuild');
    }

    public function getName(): string
    {
        return 'esbuild';
    }

    public static function createForEsbuild(
        string $targetDir,
    ): BinaryAssetManager {
        $platform = BinaryAssetManager::detectPlatform();
        $packageMap = [
            'linux-x64' => '@esbuild/linux-x64',
            'linux-arm64' => '@esbuild/linux-arm64',
            'darwin-x64' => '@esbuild/darwin-x64',
            'darwin-arm64' => '@esbuild/darwin-arm64',
        ];

        $npmPackage = $packageMap[$platform] ?? '@esbuild/linux-x64';

        return new BinaryAssetManager([
            'name' => 'esbuild',
            'source' => 'npm',
            'npm_package' => $npmPackage,
            'assets' => [
                [
                    'pattern' => 'esbuild',
                    'target' => 'esbuild',
                    'executable' => true,
                    'extract_path' => 'package/bin/esbuild',
                ],
            ],
            'target_dir' => $targetDir,
        ]);
    }
}

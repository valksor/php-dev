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

use FilesystemIterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use function array_map;
use function count;
use function explode;
use function file_exists;
use function is_dir;
use function is_readable;
use function json_decode;
use function mkdir;
use function sprintf;
use function str_contains;
use function str_replace;
use function trim;

/**
 * Generic provider for multiple npm packages from a comma-separated list.
 *
 * Usage: new GenericNpmBinaryProvider('@valksor/valksor,@valksor/ui,@valksor/icons')
 */
#[AutoconfigureTag('valksor.binary_provider')]
final readonly class GenericNpmBinaryProvider implements BinaryInterface
{
    /** @var array<int,array{package:string,tag:string}> */
    private array $packages;

    public function __construct(
        private ParameterBagInterface $bag,
        ?string $packageList = null,
    ) {
        // Read package list from constructor parameter or valksor configuration
        $packageList ??=
            $this->bag->get('valksor.build.services.binaries.options.generic_npm_packages');

        $this->packages = $packageList ?
            array_map(
                static fn (string $packageSpec): array => self::parsePackageWithTag(trim($packageSpec)),
                explode(',', $packageList),
            ) : [];
    }

    public function createManager(
        string $varDir,
        ?string $requestedName = null,
    ): ?BinaryAssetManager {
        // Return manager for the first package (for compatibility with BinaryInterface)
        if (empty($this->packages)) {
            return null;
        }

        // Use requested name if provided and it's in our packages, otherwise use first package
        $packageData = $this->packages[0] ?? null;
        $package = $packageData['package'];
        $tag = $packageData['tag'];

        if ($requestedName) {
            foreach ($this->packages as $pkg) {
                if ($pkg['package'] === $requestedName) {
                    $package = $pkg['package'];
                    $tag = $pkg['tag'];

                    break;
                }
            }
        }

        return $this->createForPackage($package, $varDir . '/' . $this->getPackageDir($package), $requestedName, $tag);
    }

    /**
     * Download all configured packages.
     *
     * @param callable|null $logger Optional logger callback
     *
     * @return array<int,string> Package versions downloaded
     *
     * @throws JsonException
     */
    public function ensureAll(
        ?callable $logger = null,
    ): array {
        // If no packages configured, return empty array
        if (empty($this->packages)) {
            return [];
        }

        $versions = [];

        foreach ($this->packages as $pkg) {
            $package = $pkg['package'];
            $tag = $pkg['tag'];
            $targetDir = $this->getTargetDirectory($package);

            // Check if package is already up-to-date before creating manager
            if ($this->isPackageUpToDate($targetDir)) {
                // Read existing version to return
                $versionFile = $targetDir . '/version.json';
                $versionData = json_decode(file_get_contents($versionFile), true, 512, JSON_THROW_ON_ERROR);
                $versions[] = $versionData['version'] ?? 'unknown';

                if ($logger) {
                    $logger(sprintf('%s assets already current (%s)', $package, $versionData['version'] ?? 'unknown'));
                }

                continue;
            }

            // Package needs update - create manager and download
            $version = $this->createForPackage($package, $targetDir, null, $tag)->ensureLatest($logger);
            $versions[] = $version;
        }

        return $versions;
    }

    public function getName(): string
    {
        return 'generic_npm';
    }

    /**
     * Get the number of configured packages.
     */
    public function getPackageCount(): int
    {
        return count($this->packages);
    }

    /**
     * Get all configured package names.
     *
     * @return array<int,string>
     */
    public function getPackages(): array
    {
        return array_map(
            static fn (array $pkg): string => $pkg['package'],
            $this->packages,
        );
    }

    /**
     * Check if this provider handles a specific package name.
     */
    public function hasPackage(
        string $packageName,
    ): bool {
        return array_any($this->packages, fn ($pkg) => $pkg['package'] === $packageName);
    }

    /**
     * Sync packages from /var to /public/vendor.
     *
     * @param callable|null $logger Optional logger callback
     *
     * @return array<int,string> Package names that were synced
     */
    public function syncToPublicVendor(
        ?callable $logger = null,
    ): array {
        if (empty($this->packages)) {
            return [];
        }

        $projectRoot = $this->bag->get('kernel.project_dir');
        $varBaseDir = $projectRoot . '/var';
        $publicVendorBaseDir = $projectRoot . '/public/vendor';

        $syncedPackages = [];

        foreach ($this->packages as $pkg) {
            $package = $pkg['package'];
            $sourceDir = $varBaseDir . '/' . $this->getPackageDir($package);
            $targetDir = $publicVendorBaseDir . '/' . $this->getPackageDir($package);

            if (is_dir($sourceDir) && $this->recursiveCopy($sourceDir, $targetDir)) {
                $syncedPackages[] = $package;

                if ($logger) {
                    $logger(sprintf('Synced %s to public/vendor', $package));
                }
            } elseif ($logger) {
                $logger(sprintf('Warning: Source directory not found for %s: %s', $package, $sourceDir));
            }
        }

        return $syncedPackages;
    }

    /**
     * Create BinaryAssetManager for a specific npm package.
     */
    private function createForPackage(
        string $package,
        string $targetDir,
        ?string $requestedName = null,
        ?string $tag = null,
    ): BinaryAssetManager {
        // Use provided tag, or parse from requested name, or default to latest
        $distTag = $tag ?? 'latest';

        if ($requestedName && str_contains($requestedName, '@')) {
            $parts = explode('@', $requestedName);

            if (count($parts) >= 3) {
                // Format: @valksor/valksor@tag
                $distTag = end($parts);
            }
        }

        return new BinaryAssetManager([
            'name' => $package,
            'source' => 'npm',
            'npm_package' => $package,
            'npm_dist_tag' => $distTag,
            'assets' => [
                [
                    'pattern' => 'package',
                    'target' => '.',
                    'executable' => false,
                    'extract_path' => 'package',
                ],
            ],
            'target_dir' => $targetDir,
        ], $this->bag);
    }

    /**
     * Convert package name to directory name.
     *
     * @valksor/valksor -> valksor
     *
     * @valksor/ui -> valksor-ui
     */
    private function getPackageDir(
        string $package,
    ): string {
        return str_replace(['/', '@'], ['-', ''], $package);
    }

    /**
     * Get the target directory for a package.
     */
    private function getTargetDirectory(
        string $package,
    ): string {
        $projectRoot = $this->bag->get('kernel.project_dir');

        return $projectRoot . '/var/' . $this->getPackageDir($package);
    }

    /**
     * Check if the target directory has valid assets.
     */
    private function hasValidAssets(
        string $targetDir,
    ): bool {
        // Basic check - ensure directory is not empty and has some expected files
        $packageJson = $targetDir . '/package.json';
        $versionJson = $targetDir . '/version.json';

        return file_exists($packageJson) && file_exists($versionJson);
    }

    /**
     * Check if a package is already up-to-date in the target directory.
     *
     * @throws JsonException
     */
    private function isPackageUpToDate(
        string $targetDir,
    ): bool {
        // Check if target directory exists
        if (!is_dir($targetDir)) {
            return false;
        }

        // Check if version.json exists and is readable
        $versionFile = $targetDir . '/version.json';

        if (!is_file($versionFile) || !is_readable($versionFile)) {
            return false;
        }

        // Read and validate version.json
        $versionData = json_decode(file_get_contents($versionFile), true, 512, JSON_THROW_ON_ERROR);

        if (!$versionData || !isset($versionData['version'])) {
            return false;
        }

        // Check if basic assets are present (avoid empty directories)
        return $this->hasValidAssets($targetDir);
    }

    /**
     * Recursively copy directory from source to target.
     */
    private function recursiveCopy(
        string $source,
        string $target,
    ): bool {
        if (!is_dir($source)) {
            return false;
        }

        if (!is_dir($target) && !mkdir($target, 0o755, true)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $targetPath = $target . '/' . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!is_dir($targetPath) && !mkdir($targetPath, 0o755, true)) {
                    return false;
                }
            } elseif (!copy($item->getPathname(), $targetPath)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse package specification in format "package" or "package@tag".
     *
     * @param string $packageSpec Package specification like "@valksor/valksor" or "@valksor/valksor@next"
     *
     * @return array{package:string, tag:string}
     */
    private static function parsePackageWithTag(
        string $packageSpec,
    ): array {
        // Find the last '@' symbol - everything after it is the tag
        $lastAtPos = strrpos($packageSpec, '@');

        if (false === $lastAtPos) {
            // No @ symbol found - use entire string as package name
            return ['package' => $packageSpec, 'tag' => 'latest'];
        }

        if (0 === $lastAtPos) {
            // @ at position 0 but no other @ found - this is just a scoped package without tag
            return ['package' => $packageSpec, 'tag' => 'latest'];
        }

        // Check if there's a '/' after the first @ (scoped package)
        $firstAtPos = strpos($packageSpec, '@');
        $slashPos = strpos($packageSpec, '/', $firstAtPos + 1);

        if (false === $slashPos || $lastAtPos < $slashPos) {
            // No slash in scope or @ is before slash - this is just a package without tag
            return ['package' => $packageSpec, 'tag' => 'latest'];
        }

        // We have a scoped package with tag: @valksor/valksor@next
        $package = substr($packageSpec, 0, $lastAtPos);
        $tag = substr($packageSpec, $lastAtPos + 1);

        return ['package' => $package, 'tag' => $tag ?: 'latest'];
    }
}

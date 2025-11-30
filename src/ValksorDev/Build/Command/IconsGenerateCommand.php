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

namespace ValksorDev\Build\Command;

use DOMDocument;
use JsonException;
use RuntimeException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use ValksorDev\Build\Binary\BinaryRegistry;
use ValksorDev\Build\Provider\ProviderRegistry;

use function array_diff;
use function array_intersect;
use function array_map;
use function array_values;
use function closedir;
use function count;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function implode;
use function in_array;
use function is_dir;
use function is_file;
use function json_decode;
use function opendir;
use function preg_match;
use function readdir;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strpos;
use function substr;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const GLOB_NOSORT;
use const JSON_THROW_ON_ERROR;

#[AsCommand(name: 'valksor:icons', description: 'Generate Twig SVG icons using Lucide icons only.')]
final class IconsGenerateCommand extends AbstractCommand
{
    private string $cacheRoot;
    private SymfonyStyle $io;
    private string $sharedIdentifier;

    public function __construct(
        ParameterBagInterface $parameterBag,
        ProviderRegistry $providerRegistry,
        private readonly BinaryRegistry $binaryRegistry,
    ) {
        parent::__construct($parameterBag, $providerRegistry);
        $this->sharedIdentifier = $this->getInfrastructureDir();
    }

    /**
     * @throws JsonException
     */
    public function __invoke(
        #[Argument(
            description: 'Generate icons for a specific app (or "shared"). Default: all',
        )]
        ?string $target,
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $this->io = $this->createSymfonyStyle($input, $output);
        $projectRoot = $this->resolveProjectRoot();
        $this->cacheRoot = $projectRoot . '/var/lucide-static';
        $this->ensureDirectory($this->cacheRoot);

        $lucideDir = $this->ensureLucideIcons();

        if (null === $lucideDir) {
            $this->io->warning('No Lucide icon source could be located. Only local and shared overrides will be used.');
        }

        $sharedIcons = $this->readJsonList($this->getInfrastructureDir() . '/assets/icons.json');
        $appIcons = $this->collectAppIcons($sharedIcons);

        $targets = $this->determineTargets($target, $sharedIcons, $appIcons);

        if ([] === $targets) {
            $this->io->warning('No icon targets found.');
            $this->cleanAllIconDirectories();

            return $this->handleCommandSuccess();
        }

        $generated = 0;

        foreach ($targets as $targetId => $iconNames) {
            $generated += $this->generateForTarget(
                $targetId,
                $iconNames,
                $lucideDir,
            );
        }

        if (0 === $generated) {
            $this->io->warning('No icons generated.');

            return $this->handleCommandSuccess();
        }

        return $this->handleCommandSuccess(sprintf('Generated %d icon file%s.', $generated, 1 === $generated ? '' : 's'), $this->io);
    }

    /**
     * Clean all known icon directories when no targets are found.
     */
    private function cleanAllIconDirectories(): void
    {
        $this->io->text('[CLEANUP] No icon targets found, cleaning all known icon directories...');

        // Clean shared icons directory
        $sharedIconsDir = $this->getInfrastructureDir() . '/templates/icons';

        if (is_dir($sharedIconsDir)) {
            $this->cleanExistingTwigIcons($sharedIconsDir);
            $this->io->text('[CLEANUP] Cleaned shared icons directory: ' . $sharedIconsDir);
        }

        // Clean app-specific icons directories
        $appsDir = $this->getAppsDir();

        if (is_dir($appsDir)) {
            $handle = opendir($appsDir);

            if (false !== $handle) {
                try {
                    while (($entry = readdir($handle)) !== false) {
                        if ('.' === $entry || '..' === $entry) {
                            continue;
                        }

                        $appIconsDir = $appsDir . '/' . $entry . '/templates/icons';

                        if (is_dir($appIconsDir)) {
                            $this->cleanExistingTwigIcons($appIconsDir);
                            $this->io->text(sprintf('[CLEANUP] Cleaned app icons directory: %s (%s)', $appIconsDir, $entry));
                        }
                    }
                } finally {
                    closedir($handle);
                }
            }
        }
    }

    private function cleanExistingTwigIcons(
        string $directory,
    ): void {
        $handle = opendir($directory);

        if (false === $handle) {
            return;
        }

        try {
            while (($entry = readdir($handle)) !== false) {
                if ('.' === $entry || '..' === $entry) {
                    continue;
                }

                if (!str_ends_with($entry, '.svg.twig')) {
                    continue;
                }

                @unlink($directory . DIRECTORY_SEPARATOR . $entry);
            }
        } finally {
            closedir($handle);
        }
    }

    /**
     * Clean up orphaned icons that are no longer in the current icon list.
     *
     * @param array<int,string> $currentIcons
     */
    private function cleanOrphanedIcons(
        string $directory,
        array $currentIcons,
    ): void {
        if (!is_dir($directory)) {
            return;
        }

        $handle = opendir($directory);

        if (false === $handle) {
            return;
        }

        try {
            while (($entry = readdir($handle)) !== false) {
                if ('.' === $entry || '..' === $entry) {
                    continue;
                }

                if (!str_ends_with($entry, '.svg.twig')) {
                    continue;
                }

                // Extract icon name from filename (remove .svg.twig extension)
                $iconName = substr($entry, 0, -9);

                // If this icon is not in the current list, remove it
                if (!in_array($iconName, $currentIcons, true)) {
                    $filePath = $directory . DIRECTORY_SEPARATOR . $entry;

                    if (@unlink($filePath)) {
                        $this->io->text(sprintf('[CLEANUP] Removed orphaned icon: %s', $iconName));
                    } else {
                        $this->io->warning(sprintf('[CLEANUP] Failed to remove orphaned icon: %s', $iconName));
                    }
                }
            }
        } finally {
            closedir($handle);
        }
    }

    /**
     * @return array<string,array<int,string>>
     */
    private function collectAppIcons(
        array $sharedIcons,
    ): array {
        $appsDir = $this->getAppsDir();
        $result = [];

        if (!is_dir($appsDir)) {
            return $result;
        }

        $handle = opendir($appsDir);

        if (false === $handle) {
            return $result;
        }

        try {
            while (($entry = readdir($handle)) !== false) {
                if ('.' === $entry || '..' === $entry) {
                    continue;
                }

                $iconsPath = $appsDir . '/' . $entry . '/assets/icons.json';

                if (!is_file($iconsPath)) {
                    continue;
                }

                $icons = $this->readJsonList($iconsPath);

                // Don't skip empty icons.json files - they need cleanup

                $duplicates = array_values(array_intersect($icons, $sharedIcons));

                if ([] !== $duplicates) {
                    $this->io->note(sprintf(
                        'App "%s" defines icons already provided by shared: %s. Shared icons will be used.',
                        $entry,
                        implode(', ', $duplicates),
                    ));
                }

                $result[$entry] = array_values(array_diff($icons, $sharedIcons));
            }
        } finally {
            closedir($handle);
        }

        return $result;
    }

    /**
     * @param array<string,array<int,string>> $appIcons
     *
     * @return array<string,array<int,string>>
     */
    private function determineTargets(
        $targetArgument,
        array $sharedIcons,
        array $appIcons,
    ): array {
        $targets = [];

        if (null === $targetArgument) {
            $targets[$this->sharedIdentifier] = $sharedIcons;

            foreach ($appIcons as $app => $icons) {
                $targets[$app] = $icons;
            }

            return $targets;
        }

        $target = (string) $targetArgument;

        $sharedIdentifier = $this->sharedIdentifier;

        if ($target === $sharedIdentifier) {
            $targets[$this->sharedIdentifier] = $sharedIcons;

            return $targets;
        }

        if (!isset($appIcons[$target]) || [] === $appIcons[$target]) {
            $this->io->warning(sprintf('No icons.json found for app "%s" or no icons defined.', $target));

            return [];
        }

        $targets[$this->sharedIdentifier] = $sharedIcons;
        $targets[$target] = $appIcons[$target];

        return $targets;
    }

    /**
     * @throws JsonException
     */
    private function ensureLucideIcons(): ?string
    {
        // First check if Lucide icons already exist locally
        $existingIconsDir = $this->findExistingLucideIcons();

        if (null !== $existingIconsDir) {
            $this->io->text(sprintf('Using existing Lucide icons from: %s', $existingIconsDir));

            return $existingIconsDir;
        }

        // If no existing icons found, download lucide-static using generic provider
        try {
            $genericProvider = $this->binaryRegistry->getGenericNpmProvider();

            if (!$genericProvider) {
                $this->io->warning('Generic NPM provider not available.');

                return null;
            }

            // Create manager for lucide-static only
            $projectRoot = $this->resolveProjectRoot();
            $varDir = $projectRoot . '/var';
            $manager = $genericProvider->createManager($varDir, 'lucide-static');

            if (!$manager) {
                $this->io->warning('lucide-static not configured in generic provider.');

                return null;
            }

            // Download lucide-static only
            $manager->ensureLatest([$this->io, 'text']);

            // Look for the icons directory in the generic provider structure
            $iconsDir = $this->locateIconsDirectory($this->cacheRoot);

            if (null === $iconsDir) {
                throw new RuntimeException('Lucide icons directory could not be located after download.');
            }

            return $iconsDir;
        } catch (RuntimeException $exception) {
            $this->io->error(sprintf('Failed to ensure Lucide icons: %s', $exception->getMessage()));

            return null;
        }
    }

    private function findExistingLucideIcons(): ?string
    {
        // Check if Lucide icons already exist in the standard cache directory
        if (!is_dir($this->cacheRoot)) {
            return null;
        }

        // For lucide-static, icons are always in the icons subdirectory
        $iconsSubdir = $this->cacheRoot . '/icons';

        if ($this->iconDirectoryLooksValid($iconsSubdir)) {
            return $iconsSubdir;
        }

        return null;
    }

    /**
     * @param array<int,string> $icons
     */
    private function generateForTarget(
        string $target,
        array $icons,
        ?string $lucideIconDir,
    ): int {
        $icons = array_map('strval', $icons);

        $sharedIdentifier = $this->sharedIdentifier;

        $icons = array_values($icons);
        $count = count($icons);

        $destination = ($target === $sharedIdentifier)
            ? $this->getInfrastructureDir() . '/templates/icons'
            : $this->getAppsDir() . '/' . $target . '/templates/icons';

        $this->ensureDirectory($destination);

        if (0 === $count) {
            $this->io->text(sprintf('[%s] No icons to generate, cleaning up any orphaned icons.', $target));
            // Clean up any orphaned icons even when no new icons are generated
            $this->cleanOrphanedIcons($destination, $icons);

            return 0;
        }

        $this->cleanExistingTwigIcons($destination);

        $generated = 0;

        foreach ($icons as $icon) {
            $source = $this->locateIconSource($icon, $lucideIconDir);

            if (null === $source) {
                $this->io->warning(sprintf('[%s] Icon "%s" not found in lucide sources.', $target, $icon));

                continue;
            }

            if ($this->writeTwigIcon($icon, $source, $destination)) {
                $generated++;
            }
        }

        // Clean up any orphaned icons after generation
        $this->cleanOrphanedIcons($destination, $icons);

        $this->io->success(sprintf('[%s] Generated %d icon%s.', $target, $generated, 1 === $generated ? '' : 's'));

        return $generated;
    }

    private function iconDirectoryLooksValid(
        string $path,
    ): bool {
        if (!is_dir($path)) {
            return false;
        }

        $files = glob($path . '/*.svg', GLOB_NOSORT);

        return false !== $files && [] !== $files;
    }

    private function locateIconSource(
        string $icon,
        ?string $lucideDir,
    ): ?string {
        // Check if this is a FontAwesome icon
        $parsed = $this->parseIconName($icon);

        if (null !== $parsed && 'fontawesome' === $parsed['type']) {
            return $this->locateFontAwesomeIcon($parsed);
        }

        // Default Lucide processing
        if (null === $lucideDir || !is_dir($lucideDir)) {
            return null;
        }

        $iconPath = rtrim($lucideDir, '/') . '/' . $icon . '.svg';

        return is_file($iconPath) ? $iconPath : null;
    }

    /**
     * Locate FontAwesome icon files using configured path
     */
    private function locateFontAwesomeIcon(array $parsed): ?string
    {
        $fontAwesomePath = $this->getFontAwesomePath();

        if (empty($fontAwesomePath)) {
            return null;
        }

        $styleDir = $fontAwesomePath . '/' . $parsed['style'];

        if (!is_dir($styleDir)) {
            return null;
        }

        $iconPath = rtrim($styleDir, '/') . '/' . $parsed['name'] . '.svg';

        return is_file($iconPath) ? $iconPath : null;
    }

    private function locateIconsDirectory(
        string $baseDir,
    ): ?string {
        // For lucide-static, icons are always in the icons subdirectory
        $iconsSubdir = $baseDir . '/icons';

        if ($this->iconDirectoryLooksValid($iconsSubdir)) {
            return $iconsSubdir;
        }

        return null;
    }

    private function readJsonList(
        string $path,
    ): array {
        if (!is_file($path)) {
            $this->io->warning(sprintf('Icons manifest missing at %s', $path));

            return [];
        }

        $raw = file_get_contents($path);

        if (false === $raw || '' === $raw) {
            return [];
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->io->warning(sprintf('Invalid JSON in %s: %s', $path, $exception->getMessage()));

            return [];
        }

        return array_map('strval', $data);
    }

    private function writeTwigIcon(
        string $icon,
        string $sourcePath,
        string $destinationDir,
    ): bool {
        // Check if this is a FontAwesome icon
        $parsed = $this->parseIconName($icon);

        if (null !== $parsed && 'fontawesome' === $parsed['type']) {
            return $this->writeFontAwesomeTwigIcon($icon, $parsed, $sourcePath, $destinationDir);
        }

        // Default Lucide processing
        return $this->writeLucideTwigIcon($icon, $sourcePath, $destinationDir);
    }

    /**
     * Parse FontAwesome icon name format: fa-<type>-<icon>
     */
    private function parseIconName(string $icon): ?array
    {
        if (!str_starts_with($icon, 'fa-')) {
            return null;
        }

        // Pattern: fa-<type>-<icon>
        if (!preg_match('/^fa-([a-z]+)-(.+)$/', $icon, $matches)) {
            return null;
        }

        $type = $matches[1]; // solid, regular, light, duotone, brands
        $iconName = $matches[2]; // the actual icon name

        $validTypes = ['solid', 'regular', 'light', 'duotone', 'brands'];

        if (!in_array($type, $validTypes, true)) {
            return null;
        }

        return [
            'type' => 'fontawesome',
            'style' => $type,
            'name' => $iconName,
        ];
    }

    /**
     * Get FontAwesome path from BuildConfiguration
     */
    private function getFontAwesomePath(): ?string
    {
        $servicesConfig = $this->parameterBag->get('valksor.build.services');
        $iconsConfig = $servicesConfig['icons']['options'] ?? [];
        $fontawesomePath = $iconsConfig['fontawesome_path'] ?? null;

        if (empty($fontawesomePath)) {
            return null;
        }

        // If path is relative, resolve it from project root using existing pattern
        if (!str_starts_with($fontawesomePath, '/')) {
            return $this->resolveProjectRoot() . '/' . $fontawesomePath;
        }

        return $fontawesomePath;
    }

    /**
     * Write FontAwesome icon with proper processing
     */
    private function writeFontAwesomeTwigIcon(
        string $icon,
        array $parsed,
        string $sourcePath,
        string $destinationDir,
    ): bool {
        $svg = file_get_contents($sourcePath);

        if (false === $svg) {
            $this->io->warning('Unable to read FontAwesome icon source ' . $sourcePath);
            return false;
        }

        // Special handling for duotone icons
        if ('duotone' === $parsed['style']) {
            return $this->writeDuotoneTwigIcon($icon, $parsed, $svg, $destinationDir);
        }

        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;

        if (!@$document->loadXML($svg)) {
            $this->io->warning(sprintf('Invalid SVG for FontAwesome icon %s (%s)', $icon, $sourcePath));
            return false;
        }

        $svgElement = $document->getElementsByTagName('svg')->item(0);

        if (null === $svgElement) {
            $this->io->warning(sprintf('SVG element missing for FontAwesome icon %s (%s)', $icon, $sourcePath));
            return false;
        }

        $viewBox = $svgElement->getAttribute('viewBox') ?: '0 0 24 24';

        $inner = '';

        foreach ($svgElement->childNodes as $child) {
            $inner .= $document->saveXML($child);
        }

        // FontAwesome icons use fill="currentColor" instead of stroke
        $wrapped = sprintf(
            '{# twig-cs-fixer-disable #}<svg xmlns="http://www.w3.org/2000/svg" viewBox="%s" fill="currentColor"%s>%s</svg>',
            $viewBox,
            'brands' === $parsed['style'] ? '' : ' stroke="currentColor" stroke-width="2"',
            $inner,
        );

        $outputPath = $destinationDir . '/' . $icon . '.svg.twig';
        file_put_contents($outputPath, $wrapped);

        return true;
    }

    /**
     * Write duotone icon with special CSS class handling
     */
    private function writeDuotoneTwigIcon(
        string $icon,
        array $parsed,
        string $svg,
        string $destinationDir,
    ): bool {
        // Extract viewBox using regex
        if (preg_match('/viewBox="([^"]+)"/', $svg, $matches)) {
            $viewBox = $matches[1];
        } else {
            $viewBox = '0 0 24 24';
        }

        // Extract content between SVG tags (skip opening and closing tags)
        if (preg_match('/<svg[^>]*>(.*)<\/svg>/s', $svg, $matches)) {
            $inner = $matches[1];
        } else {
            $inner = '';
        }

        // Ensure CSS styles are present for duotone
        if (!str_contains($inner, '.fa-secondary{opacity:.4}')) {
            $inner = '<defs><style>.fa-secondary{opacity:.4}</style></defs>' . $inner;
        }

        $wrapped = sprintf(
            '{# twig-cs-fixer-disable #}<svg xmlns="http://www.w3.org/2000/svg" viewBox="%s" fill="currentColor">%s</svg>',
            $viewBox,
            $inner,
        );

        $outputPath = $destinationDir . '/' . $icon . '.svg.twig';
        file_put_contents($outputPath, $wrapped);

        return true;
    }

    /**
     * Write Lucide icon (original logic)
     */
    private function writeLucideTwigIcon(
        string $icon,
        string $sourcePath,
        string $destinationDir,
    ): bool {
        $svg = file_get_contents($sourcePath);

        if (false === $svg) {
            $this->io->warning('Unable to read Lucide icon source ' . $sourcePath);
            return false;
        }

        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;

        if (!@$document->loadXML($svg)) {
            $this->io->warning(sprintf('Invalid SVG for Lucide icon %s (%s)', $icon, $sourcePath));
            return false;
        }

        $svgElement = $document->getElementsByTagName('svg')->item(0);

        if (null === $svgElement) {
            $this->io->warning(sprintf('SVG element missing for Lucide icon %s (%s)', $icon, $sourcePath));
            return false;
        }

        $viewBox = $svgElement->getAttribute('viewBox') ?: '0 0 24 24';

        $inner = '';

        foreach ($svgElement->childNodes as $child) {
            $inner .= $document->saveXML($child);
        }

        $wrapped = sprintf(
            '{# twig-cs-fixer-disable #}<svg xmlns="http://www.w3.org/2000/svg" viewBox="%s" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">%s</svg>',
            $viewBox,
            $inner,
        );

        $outputPath = $destinationDir . '/' . $icon . '.svg.twig';
        file_put_contents($outputPath, $wrapped);

        return true;
    }
}

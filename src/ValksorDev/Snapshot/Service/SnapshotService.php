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

namespace ValksorDev\Snapshot\Service;

use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Valksor\Bundle\Service\PathFilter;
use Valksor\Bundle\Service\PathFilterHelper;
use ValksorDev\Snapshot\Util\ContentProcessor;
use ValksorDev\Snapshot\Util\OutputGenerator;

use function array_column;
use function array_slice;
use function array_sum;
use function basename;
use function count;
use function date;
use function dirname;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function filesize;
use function getmypid;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function mkdir;
use function pathinfo;
use function realpath;
use function round;
use function str_contains;
use function str_replace;
use function strlen;
use function strtolower;
use function substr_count;
use function unlink;

use const PATHINFO_EXTENSION;

/**
 * Service for generating MCP (Markdown Context Pack) snapshots of projects.
 *
 * This service provides intelligent file scanning and content analysis to create
 * AI-optimized project documentation. It combines advanced filtering capabilities
 * with binary detection and content limiting to produce focused, useful snapshots
 * for AI consumption.
 *
 * Key Features:
 * - Multi-path scanning with configurable limits
 * - Binary file detection and exclusion
 * - Gitignore integration for intelligent filtering
 * - Content size and line limiting
 * - MCP format output generation
 * - Comprehensive file type support
 *
 * Use Cases:
 * - AI code analysis and review
 * - Project documentation generation
 * - Code base summarization
 * - Knowledge base creation
 */
final class SnapshotService
{
    private PathFilter $fileFilter;
    private SymfonyStyle $io;
    private bool $isRunning = false;

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
    ) {
        $projectRoot = $parameterBag->get('kernel.project_dir');
        $this->fileFilter = PathFilter::createDefault($projectRoot);
    }

    public function isRunning(): bool
    {
        return $this->isRunning;
    }

    public function reload(): void
    {
        // Snapshot service doesn't support reloading as it's a one-time operation
    }

    public function removePidFile(
        string $pidFile,
    ): void {
        if (is_file($pidFile)) {
            unlink($pidFile);
        }
    }

    public function setIo(
        SymfonyStyle $io,
    ): void {
        $this->io = $io;
    }

    public function start(
        array $config,
    ): int {
        $this->isRunning = true;

        try {
            // Use multiple paths if provided, otherwise use default paths
            $projectRoot = $this->parameterBag->get('kernel.project_dir');
            $paths = $config['paths'] ?? $config['path'] ?? [$projectRoot];

            if (!is_array($paths)) {
                $paths = [$paths];
            }

            // Generate output filename if not provided
            $outputFile = $config['output_file'] ?? null;

            if (null === $outputFile) {
                $timestamp = date('Y_m_d_His');
                $outputFile = "snapshot_$timestamp.mcp";
            }

            // Ensure output directory exists
            $outputDir = dirname($outputFile);

            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0o755, true);
            }

            // Get snapshot configuration from service-based structure (like hot reload)
            $snapshotConfig = $this->parameterBag->get('valksor.snapshot.options');

            // Create custom path filter with user exclusions (like hot reload)
            $excludePatterns = $snapshotConfig['exclude'] ?? [];
            $this->fileFilter = PathFilterHelper::createPathFilterWithExclusions($excludePatterns, $this->parameterBag->get('kernel.project_dir'));

            // Scan files from all paths
            $maxLines = $config['max_lines'] ?? 1000;
            $files = $this->scanMultiplePaths($paths, $maxLines, $config);

            if (empty($files)) {
                if (isset($this->io)) {
                    $this->io->warning('No files found to process.');
                }
                $this->isRunning = false;

                return 0;
            }

            // Generate output
            $projectName = basename($projectRoot);
            $stats = [
                'files_processed' => count($files),
                'total_size' => array_sum(array_column($files, 'size')),
            ];

            $output = OutputGenerator::generate($projectName, $files, $stats);

            // Write to file
            if (false === file_put_contents($outputFile, $output)) {
                if (isset($this->io)) {
                    $this->io->error("Failed to write output file: $outputFile");
                }
                $this->isRunning = false;

                return 1;
            }

            if (isset($this->io)) {
                $this->io->success("Snapshot generated: $outputFile");
                $this->io->table(
                    ['Metric', 'Value'],
                    [
                        ['Files processed', $stats['files_processed']],
                        ['Total size', round($stats['total_size'] / 1024, 2) . ' KB'],
                        ['Output file', $outputFile],
                    ],
                );
            }

            $this->isRunning = false;

            return 0;
        } catch (Exception $e) {
            if (isset($this->io)) {
                $this->io->error('Snapshot generation failed: ' . $e->getMessage());
            }
            $this->isRunning = false;

            return 1;
        }
    }

    public function stop(): void
    {
        $this->isRunning = false;
    }

    public function writePidFile(
        string $pidFile,
    ): void {
        $pid = getmypid();

        if (false !== $pid) {
            file_put_contents($pidFile, $pid);
        }
    }

    /**
     * Process a single file and return its data.
     *
     * This method handles content reading, binary detection, and line limiting
     * to ensure files are processed efficiently and safely for AI consumption.
     */
    private function processFile(
        string $path,
        string $relativePath,
        int $maxLines,
        array $config,
    ): ?array {
        try {
            $content = file_get_contents($path);

            if (false === $content) {
                return null;
            }

            // Check for binary content in case file filter missed it
            if (str_contains($content, "\x00")) {
                return null;
            }

            // Apply content processing if strip_comments is enabled
            $stripComments = $config['strip_comments'] ?? false;

            if ($stripComments) {
                $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
                $content = ContentProcessor::processContent($content, $extension, true); // Preserve empty lines for structure
            }

            // Limit lines if specified
            if ($maxLines > 0) {
                $lines = explode("\n", $content);

                if (count($lines) > $maxLines) {
                    $content = implode("\n", array_slice($lines, 0, $maxLines));
                    $content .= "\n\n# [Truncated at $maxLines lines]";
                }
            }

            return [
                'path' => $path,
                'relative_path' => $relativePath,
                'content' => $content,
                'size' => strlen($content),
                'lines' => substr_count($content, "\n") + 1,
            ];
        } catch (Exception $e) {
            if (isset($this->io) && $this->io->isVerbose()) {
                $this->io->warning("Error processing file $path: " . $e->getMessage());
            }

            return null;
        }
    }

    /**
     * Scan files from a single path with recursive directory traversal.
     *
     * This method uses RecursiveDirectoryIterator for efficient file system
     * traversal and applies filtering rules to exclude unwanted files.
     */
    private function scanFiles(
        string $path,
        int $maxLines,
        array $config,
    ): array {
        $files = [];
        $processedCount = 0;
        $realPath = realpath($path);

        if (false === $realPath) {
            return $files;
        }

        // Use RecursiveDirectoryIterator for better performance
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            $path = $fileInfo->getPathname();

            // Calculate relative path from project root, not from scan path
            $projectRoot = $this->parameterBag->get('kernel.project_dir');
            $relativePath = str_replace($projectRoot . '/', '', $path);

            if ($fileInfo->isDir()) {
                // Use PathFilter like hot reload - directory filtering
                if ($this->fileFilter->shouldIgnoreDirectory($fileInfo->getBasename())) {
                    $iterator->next(); // Skip this directory
                }
            } else {
                // Use PathFilter like hot reload - file filtering
                if ($this->fileFilter->shouldIgnorePath($relativePath)) {
                    continue;
                }

                // Apply additional snapshot-specific limits not handled by PathFilter
                $maxFileSize = ($config['max_file_size'] ?? 1024) * 1024; // Convert KB to bytes

                if ($maxFileSize > 0) {
                    $size = filesize($path);

                    if (false !== $size && $size > $maxFileSize) {
                        continue;
                    }
                }

                // Check file limit BEFORE processing this file
                $maxFiles = $config['max_files'] ?? 500;

                if ($maxFiles > 0 && $processedCount >= $maxFiles) {
                    if (isset($this->io)) {
                        $this->io->warning("Maximum file limit ($maxFiles) reached. Processed $processedCount files.");
                    }

                    break;
                }

                // Process file
                $fileData = $this->processFile($path, $relativePath, $maxLines, $config);

                if (null !== $fileData) {
                    $files[] = $fileData;
                    $processedCount++;
                }
            }
        }

        return $files;
    }

    /**
     * Scan files from multiple paths and merge results.
     *
     * This method handles path validation, file limit enforcement across
     * all paths, and merges results while maintaining processing statistics.
     */
    private function scanMultiplePaths(
        array $paths,
        int $maxLines,
        array $config,
    ): array {
        $allFiles = [];
        $processedCount = 0;

        // Get maxFiles from the dynamic config (from command line, not static config)
        $maxFiles = $config['max_files'] ?? 500;

        foreach ($paths as $path) {
            // Check file limit before scanning each path
            if ($maxFiles > 0 && $processedCount >= $maxFiles) {
                if (isset($this->io)) {
                    $this->io->warning("Maximum file limit ($maxFiles) reached. Processed $processedCount files.");
                }

                break;
            }

            // Validate path
            if (!is_dir($path)) {
                if (isset($this->io)) {
                    $this->io->warning("Path does not exist or is not a directory: $path");
                }

                continue;
            }

            // Update path for validation and scanning
            // Merge files from this path
            foreach ($this->scanFiles($path, $maxLines, $config) as $file) {
                // Check global file limit
                if ($maxFiles > 0 && $processedCount >= $maxFiles) {
                    if (isset($this->io)) {
                        $this->io->warning("Maximum file limit ($maxFiles) reached. Processed $processedCount files.");
                    }

                    break;
                }

                $allFiles[] = $file;
                $processedCount++;
            }
        }

        return $allFiles;
    }
}

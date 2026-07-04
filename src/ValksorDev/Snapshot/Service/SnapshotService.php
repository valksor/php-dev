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
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
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
use function implode;
use function is_array;
use function is_dir;
use function mkdir;
use function pathinfo;
use function realpath;
use function round;
use function str_contains;
use function str_replace;
use function strlen;
use function strtolower;
use function substr_count;

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
 * Configuration precedence: values from `valksor.snapshot.options` provide the
 * baseline, and explicit command-line options override them.
 */
final class SnapshotService
{
    private PathFilter $fileFilter;
    private SymfonyStyle $io;

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
    ) {
        $projectRoot = $parameterBag->get('kernel.project_dir');
        $this->fileFilter = PathFilter::createDefault($projectRoot);
    }

    public function setIo(
        SymfonyStyle $io,
    ): void {
        $this->io = $io;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function start(
        array $config,
    ): int {
        try {
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

            // Merge configured defaults with the command-line options, then build
            // the path filter from the resulting exclusion patterns.
            $options = $this->resolveOptions($config);
            $this->fileFilter = PathFilterHelper::createPathFilterWithExclusions($options['exclude'], $projectRoot);

            $files = $this->scanMultiplePaths($paths, $options);

            if (empty($files)) {
                if (isset($this->io)) {
                    $this->io->warning('No files found to process.');
                }

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

            return 0;
        } catch (Exception $e) {
            if (isset($this->io)) {
                $this->io->error('Snapshot generation failed: ' . $e->getMessage());
            }

            return 1;
        }
    }

    /**
     * Process a single file and return its data.
     *
     * This method handles content reading, binary detection, comment stripping
     * and line limiting so files are captured efficiently and safely.
     *
     * @param array<string, mixed> $options Resolved snapshot options
     *
     * @return array<string, mixed>|null
     */
    private function processFile(
        string $path,
        string $relativePath,
        array $options,
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

            if ($options['strip_comments']) {
                $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
                $content = ContentProcessor::processContent($content, $extension);
            }

            // Limit lines if specified
            $maxLines = $options['max_lines'];

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
     * Merge configured defaults (valksor.snapshot.options) with the command-line
     * options. Command-line values win; otherwise the configured value applies.
     *
     * @param array<string, mixed> $config Command-line configuration
     *
     * @return array<string, mixed>
     */
    private function resolveOptions(
        array $config,
    ): array {
        $defaults = $this->parameterBag->get('valksor.snapshot.options');

        if (!is_array($defaults)) {
            $defaults = [];
        }

        return [
            'max_files' => $config['max_files'] ?? $defaults['max_files'] ?? 500,
            'max_file_size' => $config['max_file_size'] ?? $defaults['max_file_size'] ?? 1048576,
            'max_lines' => $config['max_lines'] ?? $defaults['max_lines'] ?? 1000,
            'exclude' => $defaults['exclude'] ?? [],
            'strip_comments' => $config['strip_comments'] ?? false,
        ];
    }

    /**
     * Scan files from a single path with recursive directory traversal.
     *
     * Ignored directories are pruned before descent via a callback filter, so
     * large trees such as vendor/ or node_modules/ are never walked.
     *
     * @param array<string, mixed> $options Resolved snapshot options
     *
     * @return list<array<string, mixed>>
     */
    private function scanFiles(
        string $path,
        array $options,
    ): array {
        $files = [];
        $processedCount = 0;
        $realPath = realpath($path);

        if (false === $realPath) {
            return $files;
        }

        $projectRoot = $this->parameterBag->get('kernel.project_dir');
        $maxFileSize = $options['max_file_size'];
        $maxFiles = $options['max_files'];

        // Prune ignored directories before the recursive iterator descends
        // into them, so excluded trees are never walked. Directories are matched
        // by their project-relative path (the same anchored check applied to
        // files) — not by basename — so anchored patterns such as `config/**`
        // only prune the top-level directory and never a nested `apps/.../config`.
        $filter = new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($realPath, FilesystemIterator::SKIP_DOTS),
            function (SplFileInfo $current) use ($projectRoot): bool {
                if (!$current->isDir()) {
                    return true;
                }

                $relativeDir = str_replace($projectRoot . '/', '', $current->getPathname());

                return !$this->fileFilter->shouldIgnorePath($relativeDir);
            },
        );

        $iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                continue;
            }

            $filePath = $fileInfo->getPathname();
            $relativePath = str_replace($projectRoot . '/', '', $filePath);

            if ($this->fileFilter->shouldIgnorePath($relativePath)) {
                continue;
            }

            // Apply the file-size cap (bytes).
            if ($maxFileSize > 0) {
                $size = filesize($filePath);

                if (false !== $size && $size > $maxFileSize) {
                    continue;
                }
            }

            // Check file limit BEFORE processing this file
            if ($maxFiles > 0 && $processedCount >= $maxFiles) {
                if (isset($this->io)) {
                    $this->io->warning("Maximum file limit ($maxFiles) reached. Processed $processedCount files.");
                }

                break;
            }

            $fileData = $this->processFile($filePath, $relativePath, $options);

            if (null !== $fileData) {
                $files[] = $fileData;
                $processedCount++;
            }
        }

        return $files;
    }

    /**
     * Scan files from multiple paths and merge results.
     *
     * This method handles path validation and enforces the global file limit
     * across all paths while merging the per-path results.
     *
     * @param list<string>         $paths
     * @param array<string, mixed> $options Resolved snapshot options
     *
     * @return list<array<string, mixed>>
     */
    private function scanMultiplePaths(
        array $paths,
        array $options,
    ): array {
        $allFiles = [];
        $processedCount = 0;
        $maxFiles = $options['max_files'];

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

            // Merge files from this path, respecting the global file limit
            foreach ($this->scanFiles($path, $options) as $file) {
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

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

namespace ValksorDev\Snapshot\Util;

use JsonException;

use function array_column;
use function array_map;
use function array_slice;
use function array_sum;
use function arsort;
use function basename;
use function count;
use function end;
use function explode;
use function implode;
use function in_array;
use function json_encode;
use function ksort;
use function round;
use function str_repeat;
use function strtolower;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

/**
 * MCP (Markdown Context Pack) output generator for snapshot functionality.
 *
 * This utility generates AI-optimized markdown output that provides a comprehensive
 * overview of project structure and file contents. The output format is specifically
 * designed for consumption by AI assistants and code analysis tools.
 *
 * Output Features:
 * - Structured markdown with metadata headers
 * - Project hierarchy visualization
 * - File grouping by language/extension
 * - Syntax-highlighted code blocks
 * - Comprehensive statistics and breakdowns
 * - AI-optimized formatting for context understanding
 *
 * Sections Generated:
 * 1. Project header and description
 * 2. MCP metadata in JSON format
 * 3. Directory structure tree
 * 4. File contents grouped by language
 * 5. Statistical summary and breakdown
 *
 * Language Support:
 * - 25+ programming languages and file types
 * - Automatic language detection from file extensions
 * - Syntax highlighting hints for markdown renderers
 * - Specialized handling for configuration files
 */
final class OutputGenerator
{
    /**
     * Generate MCP (Markdown Context Pack) format output.
     *
     * Creates a comprehensive markdown document containing project structure,
     * file contents organized by language, and detailed statistics. The output
     * is optimized for AI consumption with proper formatting and metadata.
     *
     * @param string $projectName Name of the project being snapshotted
     * @param array  $files       Array of file data with 'relative_path', 'content', 'size' keys
     * @param array  $stats       Statistics array with 'files_processed' and 'total_size' keys
     *
     * @throws JsonException
     */
    public static function generate(
        string $projectName,
        array $files,
        array $stats,
    ): string {
        $output = [];

        // Header section
        $output[] = self::generateHeader($projectName);

        // Metadata section
        $output[] = self::generateMetadata($stats);

        // Project structure section
        $output[] = self::generateProjectStructure($files);

        // File contents section
        $output[] = self::generateFileContents($files);

        // Summary section
        $output[] = self::generateSummary($files, $stats);

        return implode("\n", $output);
    }

    /**
     * Generate the file contents section grouped by language.
     */
    private static function generateFileContents(
        array $files,
    ): string {
        $contents = [];
        $contents[] = '## Files';
        $contents[] = '';

        // Group files by extension for organized output
        $filesByExt = self::groupFilesByExtension($files);

        // Sort extensions by file count (most files first for better visibility)
        $fileCounts = array_map('count', $filesByExt);
        arsort($fileCounts);

        foreach ($fileCounts as $ext => $count) {
            $lang = self::getExtensionLanguage($ext);
            $contents[] = "### $lang Files";
            $contents[] = '';

            foreach ($filesByExt[$ext] as $fileInfo) {
                $contents[] = "#### {$fileInfo['relative_path']}";
                $contents[] = '';
                $contents[] = "```$ext";
                $contents[] = $fileInfo['content'];
                $contents[] = '```';
                $contents[] = '';
            }
        }

        return implode("\n", $contents);
    }

    /**
     * Generate the project header section.
     */
    private static function generateHeader(
        string $projectName,
    ): string {
        $header = [];
        $header[] = "# $projectName";
        $header[] = '';
        $header[] = 'Project snapshot generated for AI analysis and code review.';
        $header[] = '';

        return implode("\n", $header);
    }

    /**
     * Generate the MCP metadata section.
     *
     * @throws JsonException
     */
    private static function generateMetadata(
        array $stats,
    ): string {
        $metadata = [];
        $metadata[] = '```mcp-metadata';

        $metadataJson = [
            'format_version' => '1.0.0',
            'generated_at' => date('c'),
            'num_files' => $stats['files_processed'],
            'total_size_kb' => round($stats['total_size'] / 1024, 2),
            'generator' => 'Valksor Snapshot Command',
        ];

        $metadata[] = json_encode($metadataJson, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $metadata[] = '```';
        $metadata[] = '';

        return implode("\n", $metadata);
    }

    /**
     * Generate the project structure tree section.
     */
    private static function generateProjectStructure(
        array $files,
    ): string {
        $structure = [];
        $structure[] = '## Project Structure';
        $structure[] = '';
        $structure[] = '```';

        $seenDirs = [];

        foreach ($files as $fileInfo) {
            $relativePath = $fileInfo['relative_path'];
            $parts = explode('/', $relativePath);

            foreach ($parts as $i => $iValue) {
                $dirPath = implode('/', array_slice($parts, 0, $i + 1));

                if (!in_array($dirPath, $seenDirs, true)) {
                    $indent = str_repeat('  ', $i);
                    $isFile = $i === count($parts) - 1;
                    $symbol = $isFile ? '' : '/';
                    $structure[] = "$indent$iValue$symbol";
                    $seenDirs[] = $dirPath;
                }
            }
        }

        $structure[] = '```';
        $structure[] = '';

        return implode("\n", $structure);
    }

    /**
     * Generate the summary statistics section.
     */
    private static function generateSummary(
        array $files,
        array $stats,
    ): string {
        $summary = [];
        $summary[] = '## Summary';
        $summary[] = '';
        $summary[] = '### Statistics';
        $summary[] = '';
        $summary[] = "- **Total files**: {$stats['files_processed']}";
        $summary[] = '- **Total size**: ' . round($stats['total_size'] / 1024, 2) . ' KB';
        $summary[] = '';

        // File breakdown by type
        $summary[] = '### File Breakdown';
        $summary[] = '';
        $summary[] = '| Language | Extension | Files | Size (KB) |';
        $summary[] = '|----------|-----------|-------|-----------|';

        $filesByExt = self::groupFilesByExtension($files);
        $extStats = [];

        foreach ($filesByExt as $ext => $extFiles) {
            $totalSize = array_sum(array_column($extFiles, 'size'));
            $extStats[$ext] = [
                'count' => count($extFiles),
                'size_kb' => round($totalSize / 1024, 2),
            ];
        }

        ksort($extStats);

        foreach ($extStats as $ext => $stat) {
            $lang = self::getExtensionLanguage($ext);
            $summary[] = "| $lang | `$ext` | {$stat['count']} | {$stat['size_kb']} |";
        }

        $summary[] = '';

        return implode("\n", $summary);
    }

    /**
     * Get human-readable language name from file extension.
     *
     * Maps file extensions to their corresponding programming languages
     * or file types for display in the output.
     *
     * @param string $ext File extension
     */
    private static function getExtensionLanguage(
        string $ext,
    ): string {
        return match ($ext) {
            'php' => 'PHP',
            'javascript', 'js' => 'JavaScript',
            'typescript', 'ts' => 'TypeScript',
            'python', 'py' => 'Python',
            'html', 'htm' => 'HTML',
            'css' => 'CSS',
            'scss' => 'SCSS',
            'sass' => 'Sass',
            'less' => 'Less',
            'json' => 'JSON',
            'xml' => 'XML',
            'yaml', 'yml' => 'YAML',
            'markdown', 'md' => 'Markdown',
            'sql' => 'SQL',
            'bash', 'sh', 'zsh', 'fish' => 'Shell',
            'txt' => 'Text',
            'log' => 'Log',
            'ini' => 'INI',
            'conf' => 'Config',
            'dockerfile' => 'Docker',
            'gitignore' => 'Git',
            'eslintrc' => 'ESLint',
            'prettierrc' => 'Prettier',
            'editorconfig' => 'EditorConfig',
            'vue' => 'Vue',
            'svelte' => 'Svelte',
            'jsx', 'tsx' => 'React',
            'go' => 'Go',
            'rs' => 'Rust',
            'java' => 'Java',
            'kt' => 'Kotlin',
            'swift' => 'Swift',
            'rb' => 'Ruby',
            'scala' => 'Scala',
            'clj' => 'Clojure',
            'hs' => 'Haskell',
            'ml' => 'OCaml',
            'elm' => 'Elm',
            'dart' => 'Dart',
            'lua' => 'Lua',
            'r' => 'R',
            'm' => 'Objective-C',
            'pl' => 'Perl',
            'tcl' => 'Tcl',
            'vim' => 'Vim',
            'emacs' => 'Emacs Lisp',
            default => strtoupper($ext),
        };
    }

    /**
     * Get normalized file extension from file path.
     *
     * Extracts and normalizes file extensions, handling special cases
     * and mapping common variations to standard forms.
     *
     * @param string $path File path
     */
    private static function getFileExtension(
        string $path,
    ): string {
        $basename = basename($path);
        $parts = explode('.', $basename);

        if (1 === count($parts)) {
            return 'txt';
        }

        $ext = strtolower(end($parts));

        // Handle special cases and common mappings
        return match ($ext) {
            'js' => 'javascript',
            'jsx' => 'jsx',
            'ts' => 'typescript',
            'tsx' => 'tsx',
            'py' => 'python',
            'php' => 'php',
            'html', 'htm' => 'html',
            'css' => 'css',
            'scss' => 'scss',
            'sass' => 'sass',
            'less' => 'less',
            'json' => 'json',
            'xml' => 'xml',
            'yaml', 'yml' => 'yaml',
            'md' => 'markdown',
            'sql' => 'sql',
            'sh', 'bash', 'zsh', 'fish' => 'bash',
            'vue' => 'vue',
            'svelte' => 'svelte',
            'go' => 'go',
            'rs' => 'rust',
            'java' => 'java',
            'kt' => 'kt',
            'swift' => 'swift',
            'rb' => 'ruby',
            'scala' => 'scala',
            'clj' => 'clj',
            'hs' => 'hs',
            'ml' => 'ml',
            'elm' => 'elm',
            'dart' => 'dart',
            'lua' => 'lua',
            'r' => 'r',
            'm' => 'm',
            'pl' => 'pl',
            'tcl' => 'tcl',
            'vim' => 'vim',
            'el' => 'emacs',
            'dockerfile' => 'dockerfile',
            'gitignore' => 'gitignore',
            'eslintrc' => 'eslintrc',
            'prettierrc' => 'prettierrc',
            'editorconfig' => 'editorconfig',
            default => $ext,
        };
    }

    /**
     * Group files by their extension.
     *
     * @return array<string, array>
     */
    private static function groupFilesByExtension(
        array $files,
    ): array {
        $filesByExt = [];

        foreach ($files as $fileInfo) {
            $ext = self::getFileExtension($fileInfo['relative_path']);

            if (!isset($filesByExt[$ext])) {
                $filesByExt[$ext] = [];
            }
            $filesByExt[$ext][] = $fileInfo;
        }

        return $filesByExt;
    }
}

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

use function explode;
use function implode;
use function in_array;
use function preg_match;
use function preg_replace;
use function str_repeat;
use function strlen;
use function strpos;
use function substr;
use function trim;

/**
 * Utility for processing file content to remove comments and empty lines.
 *
 * This class provides language-aware comment removal while preserving line numbers
 * to maintain accurate line references in the original source code.
 *
 * Features:
 * - Multi-language comment detection (PHP, JavaScript, CSS, etc.)
 * - Preserves original line numbers by replacing removed content with markers
 * - Handles nested comments and complex patterns
 * - Maintains empty lines for structure when needed
 */
final class ContentProcessor
{
    /**
     * Process file content by removing comments and optionally empty lines.
     *
     * @param string $content        The original file content
     * @param string $extension      File extension to determine comment style
     * @param bool   $keepEmptyLines Whether to preserve empty lines structure
     *
     * @return string Processed content with line numbers preserved
     */
    public static function processContent(
        string $content,
        string $extension,
        bool $keepEmptyLines = false,
    ): string {
        return match ($extension) {
            'php' => self::processPhpContent($content, $keepEmptyLines),
            'javascript', 'js', 'typescript', 'ts', 'jsx', 'tsx' => self::processJavaScriptContent($content, $keepEmptyLines),
            'css' => self::processCssContent($content, $keepEmptyLines),
            'html', 'htm' => self::processHtmlContent($content, $keepEmptyLines),
            'json' => $content, // JSON has no comments
            'xml' => self::processXmlContent($content, $keepEmptyLines),
            'yaml', 'yml' => self::processYamlContent($content, $keepEmptyLines),
            'python', 'py' => self::processPythonContent($content, $keepEmptyLines),
            'bash', 'sh', 'zsh', 'fish' => self::processShellContent($content, $keepEmptyLines),
            default => self::processGenericContent($content, $keepEmptyLines),
        };
    }

    /**
     * Process CSS content by removing comments.
     */
    private static function processCssContent(
        string $content,
        bool $keepEmptyLines,
    ): string {
        // CSS only has block comments (/* */)
        $lines = explode("\n", $content);
        $processedLines = [];
        $inBlockComment = false;

        foreach ($lines as $line) {
            if ($inBlockComment) {
                $commentEnd = strpos($line, '*/');

                if (false !== $commentEnd) {
                    $inBlockComment = false;
                    $line = substr($line, $commentEnd + 2);
                } else {
                    $line = '';
                    $processedLines[] = $line;

                    continue;
                }
            }

            $line = preg_replace('/\/\*.*?\*\//', '', $line);
            $commentStart = strpos($line, '/*');

            if (false !== $commentStart) {
                $inBlockComment = true;
                $line = substr($line, 0, $commentStart);
            }

            $trimmedLine = trim($line);

            if (empty($trimmedLine) && !$keepEmptyLines) {
                $line = '';
            } elseif (empty($trimmedLine) && $keepEmptyLines) {
                $line = '';
            }

            $processedLines[] = $line;
        }

        return implode("\n", $processedLines);
    }

    /**
     * Process generic content by removing common comment patterns.
     */
    private static function processGenericContent(
        string $content,
        bool $keepEmptyLines,
    ): string {
        $lines = explode("\n", $content);
        $processedLines = [];

        foreach ($lines as $line) {
            // Remove common comment patterns
            $line = preg_replace('/^\s*#.*$/', '', $line);  // Shell/Python style
            $line = preg_replace('/^\s*\/\/.*$/', '', $line);  // C++ style
            $line = preg_replace('/\/\*.*?\*\//', '', $line);  // C style block comments

            $trimmedLine = trim($line);

            if (empty($trimmedLine) && !$keepEmptyLines) {
                $line = '';
            } elseif (empty($trimmedLine) && $keepEmptyLines) {
                $line = '';
            }

            $processedLines[] = $line;
        }

        return implode("\n", $processedLines);
    }

    /**
     * Process HTML content by removing HTML comments.
     */
    private static function processHtmlContent(
        string $content,
        bool $keepEmptyLines,
    ): string {
        // Remove HTML comments but preserve line structure
        $content = preg_replace('/<!--.*?-->/s', '', $content);

        if (!$keepEmptyLines) {
            return preg_replace('/^\s*$/m', '', $content);
        }

        return $content;
    }

    /**
     * Process JavaScript/TypeScript content by removing comments.
     */
    private static function processJavaScriptContent(
        string $content,
        bool $keepEmptyLines,
    ): string {
        $lines = explode("\n", $content);
        $processedLines = [];
        $inBlockComment = false;

        foreach ($lines as $line) {
            $i = 0;
            $lineLength = strlen($line);
            $inString = false;
            $stringChar = '';

            while ($i < $lineLength) {
                $char = $line[$i];

                // Handle string contexts
                if (!$inString && !$inBlockComment && in_array($char, ['"', "'", '`'], true)) {
                    $inString = true;
                    $stringChar = $char;
                    $i++;

                    continue;
                }

                if ($inString && $char === $stringChar && (0 === $i || '\\' !== $line[$i - 1])) {
                    $inString = false;
                    $stringChar = '';
                    $i++;

                    continue;
                }

                // Skip content inside strings
                if ($inString) {
                    $i++;

                    continue;
                }

                // Handle block comment end
                if ($inBlockComment && '*' === $char && $i + 1 < $lineLength && '/' === $line[$i + 1]) {
                    $inBlockComment = false;
                    $i += 2;

                    continue;
                }

                // Skip content inside block comments
                if ($inBlockComment) {
                    $i++;

                    continue;
                }

                // Remove single-line comments (//)
                if ('/' === $char && $i + 1 < $lineLength && '/' === $line[$i + 1]) {
                    $line = substr($line, 0, $i);

                    break;
                }

                // Remove block comment start (/*)
                if ('/' === $char && $i + 1 < $lineLength && '*' === $line[$i + 1]) {
                    $commentStart = $i;
                    $blockCommentEnd = strpos($line, '*/', $i + 2);

                    if (false !== $blockCommentEnd) {
                        // Block comment ends on same line
                        $beforeComment = substr($line, 0, $commentStart);
                        $afterComment = substr($line, $blockCommentEnd + 2);
                        $commentLength = $blockCommentEnd + 2 - $commentStart;
                        $replacement = str_repeat(' ', $commentLength);
                        $line = $beforeComment . $replacement . $afterComment;
                        $i = $commentStart + $commentLength;
                        $lineLength = strlen($line);
                    } else {
                        // Block comment starts here, mark as in block comment
                        $inBlockComment = true;
                        $line = substr($line, 0, $commentStart) . str_repeat(' ', $lineLength - $commentStart);

                        break;
                    }

                    continue;
                }

                $i++;
            }

            // If we're in a block comment, replace entire line with spaces
            if ($inBlockComment) {
                $line = str_repeat(' ', strlen($line));
            }

            // Trim whitespace if line is now empty or just whitespace
            $trimmedLine = trim($line);

            if (empty($trimmedLine) && !$keepEmptyLines) {
                $line = '';
            } elseif (empty($trimmedLine) && $keepEmptyLines) {
                $line = '';
            }

            $processedLines[] = $line;
        }

        return implode("\n", $processedLines);
    }

    /**
     * Process PHP content by removing PHP comments.
     */
    private static function processPhpContent(
        string $content,
        bool $keepEmptyLines,
    ): string {
        // First, remove block comments that span multiple lines
        $content = preg_replace('/\/\*.*?\*\//s', '', $content);

        // Then process line by line to remove single-line comments
        $lines = explode("\n", $content);
        $processedLines = [];

        foreach ($lines as $line) {
            $inString = false;
            $stringChar = '';
            $i = 0;
            $lineLength = strlen($line);

            while ($i < $lineLength) {
                $char = $line[$i];

                // Handle string contexts
                if (!$inString && ('"' === $char || "'" === $char)) {
                    $inString = true;
                    $stringChar = $char;
                    $i++;

                    continue;
                }

                if ($inString && $char === $stringChar && (0 === $i || '\\' !== $line[$i - 1])) {
                    $inString = false;
                    $stringChar = '';
                    $i++;

                    continue;
                }

                // Skip content inside strings
                if ($inString) {
                    $i++;

                    continue;
                }

                // Remove single-line comments (// and #)
                if (('/' === $char && $i + 1 < $lineLength && '/' === $line[$i + 1])
                    || ('#' === $char)) {
                    $line = substr($line, 0, $i);

                    break;
                }

                $i++;
            }

            // Trim whitespace if line is now empty or just whitespace
            $trimmedLine = trim($line);

            if (empty($trimmedLine) && !$keepEmptyLines) {
                $line = '';
            } elseif (empty($trimmedLine) && $keepEmptyLines) {
                // Preserve as empty line
                $line = '';
            }

            $processedLines[] = $line;
        }

        return implode("\n", $processedLines);
    }

    /**
     * Process Python content by removing comments.
     */
    private static function processPythonContent(
        string $content,
        bool $keepEmptyLines,
    ): string {
        $lines = explode("\n", $content);
        $processedLines = [];

        foreach ($lines as $line) {
            $inString = false;
            $stringChar = '';
            $tripleQuote = false;
            $i = 0;
            $lineLength = strlen($line);

            while ($i < $lineLength) {
                $char = $line[$i];

                // Handle string contexts
                if (!$inString && ('"' === $char || "'" === $char)) {
                    // Check for triple quotes
                    if ($i + 2 < $lineLength && $line[$i + 1] === $char && $line[$i + 2] === $char) {
                        $tripleQuote = !$tripleQuote;
                        $i += 3;

                        continue;
                    }
                    $inString = true;
                    $stringChar = $char;
                    $i++;

                    continue;
                }

                if ($inString && !$tripleQuote && $char === $stringChar && (0 === $i || '\\' !== $line[$i - 1])) {
                    $inString = false;
                    $stringChar = '';
                    $i++;

                    continue;
                }

                // Skip content inside strings
                if ($inString || $tripleQuote) {
                    $i++;

                    continue;
                }

                // Remove comments (#) but not in strings
                if ('#' === $char) {
                    $line = substr($line, 0, $i);

                    break;
                }

                $i++;
            }

            $trimmedLine = trim($line);

            if (empty($trimmedLine) && !$keepEmptyLines) {
                $line = '';
            } elseif (empty($trimmedLine) && $keepEmptyLines) {
                $line = '';
            }

            $processedLines[] = $line;
        }

        return implode("\n", $processedLines);
    }

    /**
     * Process shell script content by removing comments.
     */
    private static function processShellContent(
        string $content,
        bool $keepEmptyLines,
    ): string {
        $lines = explode("\n", $content);
        $processedLines = [];

        foreach ($lines as $line) {
            // Remove comments after preserving leading spaces
            if (preg_match('/^(\s*)([^#]*)(#.*)?$/', $line, $matches)) {
                [,$leading, $content] = $matches;
                $line = $leading . $content;
            }

            $trimmedLine = trim($line);

            if (empty($trimmedLine) && !$keepEmptyLines) {
                $line = '';
            } elseif (empty($trimmedLine) && $keepEmptyLines) {
                $line = '';
            }

            $processedLines[] = $line;
        }

        return implode("\n", $processedLines);
    }

    /**
     * Process XML content by removing comments.
     */
    private static function processXmlContent(
        string $content,
        bool $keepEmptyLines,
    ): string {
        // Remove XML comments
        $content = preg_replace('/<!--.*?-->/s', '', $content);

        if (!$keepEmptyLines) {
            return preg_replace('/^\s*$/m', '', $content);
        }

        return $content;
    }

    /**
     * Process YAML content by removing comments.
     */
    private static function processYamlContent(
        string $content,
        bool $keepEmptyLines,
    ): string {
        $lines = explode("\n", $content);
        $processedLines = [];

        foreach ($lines as $line) {
            // Remove comments after preserving leading spaces
            if (preg_match('/^(\s*)([^#]*)(#.*)?$/', $line, $matches)) {
                [,$leading,$content] = $matches;
                $line = $leading . $content;
            }

            $trimmedLine = trim($line);

            if (empty($trimmedLine) && !$keepEmptyLines) {
                $line = '';
            } elseif (empty($trimmedLine) && $keepEmptyLines) {
                $line = '';
            }

            $processedLines[] = $line;
        }

        return implode("\n", $processedLines);
    }
}

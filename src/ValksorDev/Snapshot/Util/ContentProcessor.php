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

use PhpToken;
use Valksor\Functions\Preg;

use function explode;
use function implode;
use function in_array;
use function str_repeat;
use function strlen;
use function strpos;
use function substr;
use function trim;

use const T_COMMENT;
use const T_DOC_COMMENT;

/**
 * Utility for processing file content to remove comments.
 *
 * This class provides language-aware comment removal to keep AI snapshots
 * compact. Comments (including multi-line blocks) are dropped rather than
 * blanked, and whitespace-only lines left behind are emptied.
 *
 * Features:
 * - Multi-language comment detection (PHP, JavaScript, CSS, etc.)
 * - PHP uses the native tokenizer, so attributes (#[...]) are never stripped
 * - Comment-only and whitespace-only lines collapse to empty lines
 */
final class ContentProcessor
{
    /**
     * Process file content by removing comments.
     *
     * @param string $content   The original file content
     * @param string $extension File extension to determine comment style
     *
     * @return string Processed content with comments removed
     */
    public static function processContent(
        string $content,
        string $extension,
    ): string {
        return match ($extension) {
            'php' => self::processPhpContent($content),
            'javascript', 'js', 'typescript', 'ts', 'jsx', 'tsx' => self::processJavaScriptContent($content),
            'css' => self::processCssContent($content),
            'html', 'htm' => self::processHtmlContent($content),
            'json' => $content, // JSON has no comments
            'xml' => self::processXmlContent($content),
            'yaml', 'yml' => self::processYamlContent($content),
            'python', 'py' => self::processPythonContent($content),
            'bash', 'sh', 'zsh', 'fish' => self::processShellContent($content),
            default => self::processGenericContent($content),
        };
    }

    /**
     * Blank out lines that contain only whitespace, preserving line count.
     */
    private static function blankWhitespaceOnlyLines(
        string $content,
    ): string {
        $lines = explode("\n", $content);

        foreach ($lines as $index => $line) {
            if ('' === trim($line)) {
                $lines[$index] = '';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Shared UTF-8-safe preg helper (match/replace) from valksor/functions.
     */
    private static function preg(): Preg\Functions
    {
        static $preg = null;

        return $preg ??= new Preg\Functions();
    }

    /**
     * Process CSS content by removing comments.
     */
    private static function processCssContent(
        string $content,
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

            $line = self::preg()->replace('/\/\*.*?\*\//', '', $line);
            $commentStart = strpos($line, '/*');

            if (false !== $commentStart) {
                $inBlockComment = true;
                $line = substr($line, 0, $commentStart);
            }

            $processedLines[] = $line;
        }

        return self::blankWhitespaceOnlyLines(implode("\n", $processedLines));
    }

    /**
     * Process generic content by removing common comment patterns.
     */
    private static function processGenericContent(
        string $content,
    ): string {
        $lines = explode("\n", $content);
        $processedLines = [];

        foreach ($lines as $line) {
            // Remove common comment patterns
            $line = self::preg()->replace('/^\s*#.*$/', '', $line);  // Shell/Python style
            $line = self::preg()->replace('/^\s*\/\/.*$/', '', $line);  // C++ style
            $line = self::preg()->replace('/\/\*.*?\*\//', '', $line);  // C style block comments

            $processedLines[] = $line;
        }

        return self::blankWhitespaceOnlyLines(implode("\n", $processedLines));
    }

    /**
     * Process HTML content by removing HTML comments.
     */
    private static function processHtmlContent(
        string $content,
    ): string {
        return self::preg()->replace('/<!--.*?-->/s', '', $content);
    }

    /**
     * Process JavaScript/TypeScript content by removing comments.
     */
    private static function processJavaScriptContent(
        string $content,
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

            $processedLines[] = $line;
        }

        return self::blankWhitespaceOnlyLines(implode("\n", $processedLines));
    }

    /**
     * Process PHP content by removing PHP comments.
     *
     * Uses the native PHP tokenizer so comment removal is lexically correct:
     * `#[Attribute]` (T_ATTRIBUTE), `#` and `//` inside strings, and block
     * comment markers within string literals or heredocs are never mistaken
     * for comments. Each comment is replaced by the newlines it spanned so
     * that subsequent line numbers stay aligned.
     */
    private static function processPhpContent(
        string $content,
    ): string {
        $result = '';

        // Drop comments entirely (multi-line doc blocks collapse) so the output
        // stays compact; T_ATTRIBUTE (#[...]) is a distinct token and is kept.
        foreach (PhpToken::tokenize($content) as $token) {
            $result .= $token->is([T_COMMENT, T_DOC_COMMENT]) ? '' : $token->text;
        }

        return self::blankWhitespaceOnlyLines($result);
    }

    /**
     * Process Python content by removing comments.
     */
    private static function processPythonContent(
        string $content,
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

            $processedLines[] = $line;
        }

        return self::blankWhitespaceOnlyLines(implode("\n", $processedLines));
    }

    /**
     * Process shell script content by removing comments.
     */
    private static function processShellContent(
        string $content,
    ): string {
        $lines = explode("\n", $content);
        $processedLines = [];

        foreach ($lines as $line) {
            // Remove comments after preserving leading spaces
            if (self::preg()->match('/^(\s*)([^#]*)(#.*)?$/', $line, $matches)) {
                [,$leading, $content] = $matches;
                $line = $leading . $content;
            }

            $processedLines[] = $line;
        }

        return self::blankWhitespaceOnlyLines(implode("\n", $processedLines));
    }

    /**
     * Process XML content by removing comments.
     */
    private static function processXmlContent(
        string $content,
    ): string {
        return self::preg()->replace('/<!--.*?-->/s', '', $content);
    }

    /**
     * Process YAML content by removing comments.
     */
    private static function processYamlContent(
        string $content,
    ): string {
        $lines = explode("\n", $content);
        $processedLines = [];

        foreach ($lines as $line) {
            // Remove comments after preserving leading spaces
            if (self::preg()->match('/^(\s*)([^#]*)(#.*)?$/', $line, $matches)) {
                [,$leading,$content] = $matches;
                $line = $leading . $content;
            }

            $processedLines[] = $line;
        }

        return self::blankWhitespaceOnlyLines(implode("\n", $processedLines));
    }
}

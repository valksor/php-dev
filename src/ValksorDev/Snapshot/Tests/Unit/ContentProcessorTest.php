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

namespace ValksorDev\Snapshot\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ValksorDev\Snapshot\Util\ContentProcessor;

use function explode;
use function substr_count;

/**
 * Tests for ContentProcessor comment stripping.
 *
 * Covers the language-aware comment removal, with particular attention to the
 * lexical edge cases the tokenizer-based PHP path must get right.
 */
final class ContentProcessorTest extends TestCase
{
    public function testCssBlockCommentsAreStripped(): void
    {
        $css = ".a { color: red; } /* comment */\n/* leading */ .b { top: 0; }\n";
        $out = ContentProcessor::processContent($css, 'css');

        self::assertStringNotContainsString('comment', $out);
        self::assertStringContainsString('.a { color: red; }', $out);
        self::assertStringContainsString('.b { top: 0; }', $out);
    }

    public function testGenericStrippingIsUsedForUnknownExtensions(): void
    {
        // The generic processor removes full-line // and # comments and inline
        // block comments.
        $out = ContentProcessor::processContent("// full comment\n# hashed\nvalue /* block */ x\nkept\n", 'unknownext');

        self::assertStringNotContainsString('full comment', $out);
        self::assertStringNotContainsString('hashed', $out);
        self::assertStringNotContainsString('block', $out);
        self::assertStringContainsString('value', $out);
        self::assertStringContainsString('kept', $out);
    }

    public function testHtmlCommentsAreStripped(): void
    {
        $html = "<div>a</div>\n<!-- multi\nline\ncomment -->\n<span>b</span>\n";
        $out = ContentProcessor::processContent($html, 'html');

        self::assertStringNotContainsString('comment', $out);
        self::assertStringContainsString('<div>a</div>', $out);
        self::assertStringContainsString('<span>b</span>', $out);
    }

    public function testJavaScriptCommentsAreStripped(): void
    {
        $js = "const a = 1; // inline\n/* block */\nconst b = 2;\n";
        $out = ContentProcessor::processContent($js, 'js');

        self::assertStringNotContainsString('inline', $out);
        self::assertStringNotContainsString('block', $out);
        self::assertStringContainsString('const a = 1;', $out);
        self::assertStringContainsString('const b = 2;', $out);
    }

    public function testJavaScriptDoubleSlashInStringIsKept(): void
    {
        $js = "const url = \"http://example.com\";\n";
        $out = ContentProcessor::processContent($js, 'js');

        self::assertStringContainsString('http://example.com', $out);
    }

    public function testJsonIsReturnedUnchanged(): void
    {
        $json = "{\n  \"key\": \"value\",\n  \"n\": 1\n}\n";

        self::assertSame($json, ContentProcessor::processContent($json, 'json'));
    }

    public function testMultiLinePhpCommentsAreCollapsed(): void
    {
        // A multi-line block comment must not leave blank lines behind — the
        // output stays as compact as the pre-tokenizer behaviour.
        $php = "<?php\n\$a = 1;\n/* one\ntwo\nthree */\n\$b = 2;\n";
        $out = ContentProcessor::processContent($php, 'php');

        self::assertStringNotContainsString('two', $out);
        self::assertStringContainsString('$a = 1;', $out);
        self::assertStringContainsString('$b = 2;', $out);
        self::assertLessThan(substr_count($php, "\n"), substr_count($out, "\n"));
    }

    public function testPhpBlockCommentMarkerInsideStringIsKept(): void
    {
        $php = "<?php\n\$s = '/* not a comment */';\n";
        $out = ContentProcessor::processContent($php, 'php');

        self::assertStringContainsString("'/* not a comment */'", $out);
    }

    public function testPhpCommentsAreStripped(): void
    {
        $php = "<?php\n// line comment\n\$a = 1; # hash comment\n/* block */\n\$b = 2;\n";
        $out = ContentProcessor::processContent($php, 'php');

        self::assertStringNotContainsString('line comment', $out);
        self::assertStringNotContainsString('hash comment', $out);
        self::assertStringNotContainsString('block', $out);
        self::assertStringContainsString('$a = 1;', $out);
        self::assertStringContainsString('$b = 2;', $out);
    }

    public function testPhpDocBlockIsStripped(): void
    {
        $php = "<?php\n/**\n * Doc block.\n */\nfunction f(): void {}\n";
        $out = ContentProcessor::processContent($php, 'php');

        self::assertStringNotContainsString('Doc block', $out);
        self::assertStringContainsString('function f(): void {}', $out);
    }

    public function testPhpEightAttributesArePreserved(): void
    {
        $php = <<<'PHP'
            <?php

            #[AsCommand(name: 'app:run')]
            #[Route('/path')]
            final class Foo {}
            PHP;

        $out = ContentProcessor::processContent($php, 'php');

        self::assertStringContainsString("#[AsCommand(name: 'app:run')]", $out);
        self::assertStringContainsString("#[Route('/path')]", $out);
    }

    public function testPhpHeredocContentIsPreserved(): void
    {
        $php = "<?php\n\$q = <<<SQL\n-- keep this dashed line\nSELECT 1; # keep this hash\nSQL;\n";
        $out = ContentProcessor::processContent($php, 'php');

        self::assertStringContainsString('-- keep this dashed line', $out);
        self::assertStringContainsString('SELECT 1; # keep this hash', $out);
    }

    public function testPythonCommentsAreStripped(): void
    {
        $py = "x = 1  # comment\nurl = '#not-a-comment'\n";
        $out = ContentProcessor::processContent($py, 'py');

        self::assertStringNotContainsString('# comment', $out);
        self::assertStringContainsString("url = '#not-a-comment'", $out);
    }

    public function testShellCommentsAreStripped(): void
    {
        $sh = "echo hi # trailing\n# full line\nls -la\n";
        $out = ContentProcessor::processContent($sh, 'sh');

        self::assertStringNotContainsString('trailing', $out);
        self::assertStringNotContainsString('full line', $out);
        self::assertStringContainsString('echo hi', $out);
        self::assertStringContainsString('ls -la', $out);
    }

    public function testWhitespaceOnlyLinesAreBlanked(): void
    {
        $php = "<?php\n    // only a comment\n\$x = 1;\n";
        $out = ContentProcessor::processContent($php, 'php');
        $lines = explode("\n", $out);

        // The comment-only line collapses to an empty string, not indentation.
        self::assertSame('', $lines[1]);
    }

    public function testXmlCommentsAreStripped(): void
    {
        $xml = "<root>\n  <!-- comment -->\n  <child/>\n</root>\n";
        $out = ContentProcessor::processContent($xml, 'xml');

        self::assertStringNotContainsString('comment', $out);
        self::assertStringContainsString('<child/>', $out);
    }

    public function testYamlCommentsAreStripped(): void
    {
        $yaml = "key: value # trailing\n# full line\nother: 1\n";
        $out = ContentProcessor::processContent($yaml, 'yaml');

        self::assertStringNotContainsString('trailing', $out);
        self::assertStringNotContainsString('full line', $out);
        self::assertStringContainsString('key: value', $out);
        self::assertStringContainsString('other: 1', $out);
    }
}

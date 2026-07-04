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

namespace ValksorDev\PhpCsFixerCustomFixers\Tests;

use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\TestCase;
use ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\Analyzer\Analyzer;
use ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\Exception\TokenAnalysisException;

final class AnalyzerTest extends TestCase
{
    public function testGetClosingCurlyBracketMatchesOpening(): void
    {
        $tokens = Tokens::fromCode('<?php class C { public int $x = 1; }');
        $analyzer = new Analyzer($tokens);

        $open = (int) $tokens->getNextTokenOfKind(0, ['{']);
        $close = $analyzer->getClosingCurlyBracket($open);

        self::assertNotNull($close);
        self::assertSame('}', $tokens[$close]->getContent());
    }

    public function testGetClosingParenthesisMatchesOpening(): void
    {
        $tokens = Tokens::fromCode('<?php f($a, $b);');
        $analyzer = new Analyzer($tokens);

        $open = (int) $tokens->getNextTokenOfKind(0, ['(']);
        $close = $analyzer->getClosingParenthesis($open);

        self::assertNotNull($close);
        self::assertSame(')', $tokens[$close]->getContent());
    }

    public function testMagicCallDelegatesToTokensAnalyzer(): void
    {
        $tokens = Tokens::fromCode('<?php class C { public int $x = 1; }');
        $analyzer = new Analyzer($tokens);

        // getClassyElements() is a real TokensAnalyzer method reached via __call.
        self::assertNotEmpty($analyzer->getClassyElements());
    }

    public function testMagicCallThrowsForUnknownMethod(): void
    {
        $analyzer = new Analyzer(Tokens::fromCode('<?php $x = 1;'));

        $this->expectException(TokenAnalysisException::class);

        /* @phpstan-ignore-next-line method.notFound */
        $analyzer->thisMethodDoesNotExist();
    }
}

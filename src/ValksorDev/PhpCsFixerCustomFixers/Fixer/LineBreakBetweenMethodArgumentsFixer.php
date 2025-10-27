<?php declare(strict_types = 1);

/*
 * This file is part of the Valksor package.
 *
 * (c) Dāvis Zālītis (k0d3r1s)
 * (c) SIA Valksor <packages@valksor.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ValksorDev\PhpCsFixerCustomFixers\Fixer;

use Exception;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;
use ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\AbstractFixer;

use function array_filter;
use function array_reverse;
use function assert;
use function is_int;
use function sort;

use const T_FUNCTION;
use const T_STRING;
use const T_WHITESPACE;

final class LineBreakBetweenMethodArgumentsFixer extends AbstractFixer
{
    public const int T_TYPEHINT_SEMI_COLON = 10025;

    public function getDocumentation(): string
    {
        return 'Move function arguments to separate lines (one argument per line)';
    }

    public function getSampleCode(): string
    {
        return <<<'SPEC'
            <?php declare(strict_types = 1);

            namespace Project\Namespace;

            class Test
            {
                public function fun1(string $arg1, array $arg2 = [], ?float $arg3 = null)
                {
                    return;
                }

                public function fun2(float $arg1, array $arg2 = [], \ArrayAccess $arg3 = null, bool $bool = true, \Iterator $thisLastArgument = null)
                {
                    return;
                }

                public function fun3(string $arg1,
                    array $arg2 = []
                ) {
                    return;
                }
            }
            SPEC;
    }

    /**
     * @throws Exception
     */
    protected function applyFix(
        SplFileInfo $file,
        Tokens $tokens,
    ): void {
        $functions = array_filter($tokens->toArray(), static fn ($token) => T_FUNCTION === $token->getId());

        foreach (array_reverse($functions, true) as $index => $token) {
            $nextIndex = $tokens->getNextMeaningfulToken($index);
            $next = $tokens[$nextIndex];

            if (null === $nextIndex) {
                continue;
            }

            if (T_STRING !== $next->getId()) {
                continue;
            }

            $openBraceIndex = $tokens->getNextMeaningfulToken($nextIndex);
            $openBrace = $tokens[$openBraceIndex];

            if ('(' !== $openBrace->getContent()) {
                continue;
            }

            if (0 === $this->analyze($tokens)->getNumberOfArguments($index)) {
                $this->mergeArgs($tokens, $index);

                continue;
            }

            if ($this->analyze($tokens)->getSizeOfTheLine($index) > 1) {
                $this->splitArgs($tokens, $index);

                continue;
            }

            $clonedTokens = clone $tokens;
            $this->mergeArgs($clonedTokens, $index);

            if ($this->analyze($clonedTokens)->getSizeOfTheLine($index) > 1) {
                $this->splitArgs($tokens, $index);
            } else {
                $this->mergeArgs($tokens, $index);
            }
        }
    }

    /**
     * @throws Exception
     */
    private function mergeArgs(
        Tokens $tokens,
        int $index,
    ): void {
        $openBraceIndex = $tokens->getNextTokenOfKind($index, ['(']);
        $closeBraceIndex = $this->analyze($tokens)->getClosingParenthesis($openBraceIndex);

        foreach ($tokens->findGivenKind(T_WHITESPACE, $openBraceIndex, $closeBraceIndex) as $spaceIndex => $spaceToken) {
            $tokens[$spaceIndex] = new Token([T_WHITESPACE, ' ']);
        }

        $tokens->removeTrailingWhitespace($openBraceIndex);
        $tokens->removeLeadingWhitespace($closeBraceIndex);

        $end = $tokens->getNextTokenOfKind($closeBraceIndex, [';', '{']);

        if ('{' === $tokens[$end]->getContent()) {
            $tokens->removeLeadingWhitespace($end);
            $tokens->ensureWhitespaceAtIndex($end, -1, "\n" . $this->analyze($tokens)->getLineIndentation($index));
        }
    }

    /**
     * @throws Exception
     */
    private function splitArgs(
        Tokens $tokens,
        int $index,
    ): void {
        $this->mergeArgs($tokens, $index);

        $openBraceIndex = (int) $tokens->getNextTokenOfKind($index, ['(']);
        $closeBraceIndex = $this->analyze($tokens)->getClosingParenthesis($openBraceIndex);

        if (0 === $closeBraceIndex) {
            return;
        }

        if ('{' === $tokens[$tokens->getNextMeaningfulToken($closeBraceIndex)]->getContent()) {
            $tokens->removeTrailingWhitespace($closeBraceIndex);
            $tokens->ensureWhitespaceAtIndex($closeBraceIndex, 1, ' ');
        }

        if ($tokens[$tokens->getNextMeaningfulToken($closeBraceIndex)]->isGivenKind(self::T_TYPEHINT_SEMI_COLON)) {
            $end = $tokens->getNextTokenOfKind($closeBraceIndex, [';', '{']);

            $tokens->removeLeadingWhitespace($end);

            if (';' !== $tokens[$end]->getContent()) {
                $tokens->ensureWhitespaceAtIndex($end, 0, ' ');
            }
        }

        $linebreaks = [$openBraceIndex, $closeBraceIndex - 1];
        assert(is_int($closeBraceIndex));

        for ($i = $openBraceIndex + 1; $i < $closeBraceIndex; $i++) {
            if ('(' === $tokens[$i]->getContent()) {
                $i = $this->analyze($tokens)->getClosingParenthesis($i);
            }

            if ('[' === $tokens[$i]->getContent()) {
                $i = $this->analyze($tokens)->getClosingBracket($i);
            }

            if (',' === $tokens[$i]->getContent()) {
                $linebreaks[] = $i;
            }
        }

        sort($linebreaks);

        foreach (array_reverse($linebreaks) as $iteration => $linebreak) {
            $tokens->removeTrailingWhitespace($linebreak);

            $whitespace = match ($iteration) {
                0 => "\n" . $this->analyze($tokens)->getLineIndentation($index),
                default => "\n" . $this->analyze($tokens)->getLineIndentation($index) . '    ',
            };

            $tokens->ensureWhitespaceAtIndex($linebreak, 1, $whitespace);
        }
    }
}

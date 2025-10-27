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

use PhpCsFixer\Tokenizer\Analyzer\FunctionsAnalyzer;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;
use ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\AbstractFixer;

use function assert;
use function is_int;
use function str_repeat;
use function substr;

use const T_CONSTANT_ENCAPSED_STRING;
use const T_DIR;
use const T_LNUMBER;
use const T_NS_SEPARATOR;
use const T_STRING;

final class NoUselessDirnameCallFixer extends AbstractFixer
{
    public function applyFix(
        SplFileInfo $file,
        Tokens $tokens,
    ): void {
        for ($index = $tokens->count() - 1; $index > 0; $index--) {
            if (!$tokens[$index]->isGivenKind(T_DIR)) {
                continue;
            }

            $prevInserts = $this->getPrevTokensUpdates($tokens, $index);

            if (null === $prevInserts) {
                continue;
            }

            $nextInserts = $this->getNextTokensUpdates($tokens, $index);

            if (null === $nextInserts) {
                continue;
            }

            foreach ($prevInserts + $nextInserts as $i => $content) {
                if ('' === $content) {
                    $tokens->clearTokenAndMergeSurroundingWhitespace($i);
                } else {
                    $tokens[$i] = new Token([T_CONSTANT_ENCAPSED_STRING, $content]);
                }
            }
        }
    }

    public function getDocumentation(): string
    {
        return 'There must be no useless `dirname` calls.';
    }

    public function getSampleCode(): string
    {
        return '<?php
            require dirname(__DIR__) . "/vendor/autoload.php";
        ';
    }

    public function isCandidate(
        Tokens $tokens,
    ): bool {
        return $tokens->isTokenKindFound(T_DIR);
    }

    private function getNextTokensUpdates(
        Tokens $tokens,
        int $index,
    ): ?array {
        $depthLevel = 1;
        $updates = [];

        $commaOrClosingParenthesisIndex = $tokens->getNextMeaningfulToken($index);
        assert(is_int($commaOrClosingParenthesisIndex));

        if ($tokens[$commaOrClosingParenthesisIndex]->equals(',')) {
            $updates[$commaOrClosingParenthesisIndex] = '';
            $afterCommaIndex = $tokens->getNextMeaningfulToken($commaOrClosingParenthesisIndex);
            assert(is_int($afterCommaIndex));

            if ($tokens[$afterCommaIndex]->isGivenKind(T_LNUMBER)) {
                $depthLevel = (int) $tokens[$afterCommaIndex]->getContent();
                $updates[$afterCommaIndex] = '';
                $commaOrClosingParenthesisIndex = $tokens->getNextMeaningfulToken($afterCommaIndex);
                assert(is_int($commaOrClosingParenthesisIndex));
            }
        }

        if ($tokens[$commaOrClosingParenthesisIndex]->equals(',')) {
            $updates[$commaOrClosingParenthesisIndex] = '';
            $commaOrClosingParenthesisIndex = $tokens->getNextMeaningfulToken($commaOrClosingParenthesisIndex);
            assert(is_int($commaOrClosingParenthesisIndex));
        }
        $closingParenthesisIndex = $commaOrClosingParenthesisIndex;

        if (!$tokens[$closingParenthesisIndex]->equals(')')) {
            return null;
        }
        $updates[$closingParenthesisIndex] = '';

        $concatenationIndex = $tokens->getNextMeaningfulToken($closingParenthesisIndex);
        assert(is_int($concatenationIndex));

        if (!$tokens[$concatenationIndex]->equals('.')) {
            return null;
        }

        $stringIndex = $tokens->getNextMeaningfulToken($concatenationIndex);
        assert(is_int($stringIndex));

        if (!$tokens[$stringIndex]->isGivenKind(T_CONSTANT_ENCAPSED_STRING)) {
            return null;
        }

        $stringContent = $tokens[$stringIndex]->getContent();
        $updates[$stringIndex] = $stringContent[0] . str_repeat('/..', $depthLevel) . substr($stringContent, 1);

        return $updates;
    }

    private function getPrevTokensUpdates(
        Tokens $tokens,
        int $index,
    ): ?array {
        $updates = [];

        $openParenthesisIndex = $tokens->getPrevMeaningfulToken($index);
        assert(is_int($openParenthesisIndex));

        if (!$tokens[$openParenthesisIndex]->equals('(')) {
            return null;
        }
        $updates[$openParenthesisIndex] = '';

        $dirnameCallIndex = $tokens->getPrevMeaningfulToken($openParenthesisIndex);
        assert(is_int($dirnameCallIndex));

        if (!$tokens[$dirnameCallIndex]->equals([T_STRING, 'dirname'], false)) {
            return null;
        }

        if (!new FunctionsAnalyzer()->isGlobalFunctionCall($tokens, $dirnameCallIndex)) {
            return null;
        }
        $updates[$dirnameCallIndex] = '';

        $namespaceSeparatorIndex = $tokens->getPrevMeaningfulToken($dirnameCallIndex);
        assert(is_int($namespaceSeparatorIndex));

        if ($tokens[$namespaceSeparatorIndex]->isGivenKind(T_NS_SEPARATOR)) {
            $updates[$namespaceSeparatorIndex] = '';
        }

        return $updates;
    }
}

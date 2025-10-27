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

use function array_key_exists;
use function array_keys;
use function array_pad;
use function array_slice;
use function count;
use function explode;
use function implode;

use const T_DO;
use const T_FOR;
use const T_FOREACH;
use const T_IF;
use const T_SWITCH;
use const T_WHILE;
use const T_WHITESPACE;

final class LineBreakBetweenStatementsFixer extends AbstractFixer
{
    private const array HANDLERS = [
        T_DO => 'do',
        T_FOR => 'common',
        T_FOREACH => 'common',
        T_IF => 'common',
        T_SWITCH => 'common',
        T_WHILE => 'common',
    ];

    public function getDocumentation(): string
    {
        return 'Each statement (in, for, foreach, ...) MUST BE separated by an empty line';
    }

    public function getSampleCode(): string
    {
        return <<<'PHP'
            <?php

            namespace Project\Namespace;

            class Test
            {
                public function test() {
                    do {
                        // ...
                    } while (true);
                    foreach (['foo', 'bar'] as $str) {
                        // ...
                    }
                    if (true === false) {
                        // ...
                    }


                    while (true) {
                        // ...
                    }
                }
            }
            PHP;
    }

    /**
     * @throws Exception
     */
    protected function applyFix(
        SplFileInfo $file,
        Tokens $tokens,
    ): void {
        foreach ($tokens->findGivenKind(array_keys(self::HANDLERS)) as $kind => $matchedTokens) {
            match (self::HANDLERS[$kind]) {
                'do' => $this->handleDo($matchedTokens, $tokens),
                default => $this->handleCommon($matchedTokens, $tokens),
            };
        }
    }

    private function ensureNumberOfBreaks(
        string $whitespace,
    ): string {
        $parts = explode("\n", $whitespace);
        $currentCount = count($parts);
        $desiredCount = 3;

        if ($currentCount > $desiredCount) {
            $parts = array_slice($parts, $currentCount - $desiredCount);
        }

        if ($currentCount < $desiredCount) {
            $parts = array_pad($parts, -$desiredCount, '');
        }

        return implode("\n", $parts);
    }

    private function fixSpaces(
        int $index,
        Tokens $tokens,
    ): void {
        $space = $index + 1;

        if (!$tokens[$space]->isWhitespace()) {
            return;
        }

        $nextMeaningful = $tokens->getNextMeaningfulToken($index);

        if (null === $nextMeaningful) {
            return;
        }

        if (!array_key_exists($tokens[$nextMeaningful]->getId(), self::HANDLERS)) {
            return;
        }

        $tokens[$space] = new Token([T_WHITESPACE, $this->ensureNumberOfBreaks($tokens[$space]->getContent())]);
    }

    /**
     * @throws Exception
     */
    private function handleCommon(
        array $matchedTokens,
        Tokens $tokens,
    ): void {
        foreach ($matchedTokens as $index => $token) {
            $curlyBracket = $tokens->findSequence([
                '{',
            ], $index);

            if (null === $curlyBracket) {
                continue;
            }

            $openCurlyBracket = array_key_first($curlyBracket);

            $closeCurlyBracket = $this->analyze($tokens)->getClosingCurlyBracket($openCurlyBracket);

            if (null === $closeCurlyBracket) {
                continue;
            }

            $this->fixSpaces(
                $closeCurlyBracket,
                $tokens,
            );
        }
    }

    /**
     * @throws Exception
     */
    private function handleDo(
        array $matchedTokens,
        Tokens $tokens,
    ): void {
        foreach ($matchedTokens as $index => $token) {
            $this->fixSpaces(
                $this->analyze($tokens)->getNextSemiColon($index),
                $tokens,
            );
        }
    }
}

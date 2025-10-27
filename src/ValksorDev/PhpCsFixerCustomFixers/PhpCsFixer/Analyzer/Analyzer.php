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

namespace ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\Analyzer;

use InvalidArgumentException;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;
use Valksor\Functions\Preg;
use ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\Analyzer\Element\CaseElement;
use ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\Analyzer\Element\Constructor;
use ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\Analyzer\Element\SwitchElement;

use function assert;
use function call_user_func_array;
use function count;
use function current;
use function end;
use function explode;
use function is_array;
use function is_bool;
use function is_int;
use function mb_strlen;
use function mb_strpos;
use function sprintf;

use const T_CASE;
use const T_CLASS;
use const T_DEFAULT;
use const T_ENDSWITCH;
use const T_STRING;
use const T_SWITCH;

/**
 * @internal
 */
final class Analyzer
{
    private TokensAnalyzer $analyzer;

    public function __construct(
        private readonly Tokens $tokens,
    ) {
        $this->analyzer = new TokensAnalyzer($tokens);
    }

    public function __call(
        string $name,
        array $arguments,
    ): mixed {
        return call_user_func_array([$this->analyzer, $name], $arguments);
    }

    public function findNonAbstractConstructor(
        int $classIndex,
    ): ?Constructor {
        if (!$this->tokens[$classIndex]->isGivenKind(T_CLASS)) {
            throw new InvalidArgumentException(sprintf('Index %d is not a class.', $classIndex));
        }

        foreach ($this->analyzer->getClassyElements() as $index => $element) {
            if ($element['classIndex'] !== $classIndex) {
                continue;
            }

            if (!$this->isConstructor($index, $element)) {
                continue;
            }

            $constructorAttributes = $this->analyzer->getMethodAttributes($index);

            if ($constructorAttributes['abstract']) {
                return null;
            }

            return new Constructor($this->tokens, $index);
        }

        return null;
    }

    public function getBeginningOfTheLine(
        int $index,
    ): ?int {
        for ($i = $index; $i >= 0; $i--) {
            if (false !== mb_strpos($this->tokens[$i]->getContent(), "\n")) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @throws Exception
     */
    public function getClosingBracket(
        int $index,
    ): ?int {
        return $this->findBlockEndMatchingOpeningToken($index, ']', '[');
    }

    /**
     * @throws Exception
     */
    public function getClosingCurlyBracket(
        int $index,
    ): ?int {
        return $this->findBlockEndMatchingOpeningToken($index, '}', '{');
    }

    /**
     * @throws Exception
     */
    public function getClosingParenthesis(
        int $index,
    ): ?int {
        return $this->findBlockEndMatchingOpeningToken($index, ')', '(');
    }

    public function getEndOfTheLine(
        int $index,
    ): ?int {
        for ($i = $index; $i < $this->tokens->count(); $i++) {
            if (false !== mb_strpos($this->tokens[$i]->getContent(), "\n")) {
                return $i;
            }
        }

        return null;
    }

    public function getLineIndentation(
        int $index,
    ): string {
        $start = $this->getBeginningOfTheLine($index);
        $token = $this->tokens[$start];
        $parts = explode("\n", $token->getContent());

        $result = end($parts);

        if (!is_bool($result)) {
            return (string) $result;
        }

        return '';
    }

    /**
     * @throws Exception
     */
    public function getMethodArguments(
        int $index,
    ): array {
        $methodName = $this->tokens->getNextMeaningfulToken($index);
        $openParenthesis = (int) $this->tokens->getNextMeaningfulToken($methodName);
        $closeParenthesis = $this->getClosingParenthesis($openParenthesis);

        $arguments = [];

        static $_helper = null;

        if (null === $_helper) {
            $_helper = new class {
                use Preg\Traits\_Match;
            };
        }

        for ($position = $openParenthesis + 1; $position < $closeParenthesis; $position++) {
            $token = $this->tokens[$position];

            if ($token->isWhitespace()) {
                continue;
            }

            $argumentType = null;
            $argumentName = $position;
            $argumentAsDefault = false;
            $argumentNullable = false;

            if (!$_helper->match('/^\$.+/', $this->tokens[$argumentName]->getContent())) {
                do {
                    if (!$this->tokens[$argumentName]->isWhitespace()) {
                        $argumentType .= $this->tokens[$argumentName]->getContent();
                    }

                    $argumentName++;
                } while (!$_helper->match('/^\$.+/', $this->tokens[$argumentName]->getContent()));
            }

            $next = $this->tokens->getNextMeaningfulToken($argumentName);

            if ('=' === $this->tokens[$next]->getContent()) {
                $argumentAsDefault = true;
                $value = $this->tokens->getNextMeaningfulToken($next);
                $argumentNullable = 'null' === $this->tokens[$value]->getContent();
            }

            $arguments[$position] = [
                'type' => $argumentType,
                'name' => $this->tokens[$argumentName]->getContent(),
                'nullable' => $argumentNullable,
                'asDefault' => $argumentAsDefault,
            ];

            $nextComma = $this->getNextComma($position);

            if (null === $nextComma) {
                return $arguments;
            }

            $position = $nextComma;
        }

        return $arguments;
    }

    /**
     * @throws Exception
     */
    public function getNextComma(
        ?int $index,
    ): ?int {
        return $this->findBlockEndMatchingOpeningToken($index, ',', ['(', '[', '{']);
    }

    /**
     * @throws Exception
     */
    public function getNextSemiColon(
        ?int $index,
    ): ?int {
        return $this->findBlockEndMatchingOpeningToken($index, ';', ['(', '[', '{']);
    }

    /**
     * @throws Exception
     */
    public function getNumberOfArguments(
        int $index,
    ): int {
        return count($this->getMethodArguments($index));
    }

    public function getSizeOfTheLine(
        int $index,
    ): int {
        $start = (int) $this->getBeginningOfTheLine($index);
        $end = $this->getEndOfTheLine($index);
        $size = 0;

        $parts = explode("\n", $this->tokens[$start]->getContent());
        $size += mb_strlen(end($parts));

        $parts = explode("\n", $this->tokens[$end]->getContent());
        $size += mb_strlen(current($parts));

        for ($i = $start + 1; $i < $end; $i++) {
            $size += mb_strlen($this->tokens[$i]->getContent());
        }

        return $size;
    }

    public function getSwitchAnalysis(
        int $switchIndex,
    ): SwitchElement {
        if (!$this->tokens[$switchIndex]->isGivenKind(T_SWITCH)) {
            throw new InvalidArgumentException(sprintf('Index %d is not "switch".', $switchIndex));
        }

        $casesStartIndex = $this->getCasesStart($switchIndex);
        $casesEndIndex = $this->getCasesEnd($casesStartIndex);

        $cases = [];
        $index = $casesStartIndex;

        while ($index < $casesEndIndex) {
            $index = $this->getNextSameLevelToken($index);

            if (!$this->tokens[$index]->isGivenKind([T_CASE, T_DEFAULT])) {
                continue;
            }

            $cases[] = $this->getCaseAnalysis($index);
        }

        return new SwitchElement($casesStartIndex, $casesEndIndex, $cases);
    }

    /**
     * @throws Exception
     */
    private function findBlockEndMatchingOpeningToken(
        ?int $index,
        string|int $closingToken,
        string|int|array $openingToken,
    ): ?int {
        do {
            $index = $this->tokens->getNextMeaningfulToken($index);

            if (null === $index) {
                return null;
            }

            if (is_array($openingToken)) {
                foreach ($openingToken as $opening) {
                    if ($opening === $this->tokens[$index]->getContent()) {
                        $index = $this->getClosingMatchingToken($index, $opening);

                        break;
                    }
                }
            } elseif ($openingToken === $this->tokens[$index]->getContent()) {
                $index = $this->getClosingMatchingToken($index, $openingToken);
            }
        } while ($closingToken !== $this->tokens[$index]->getContent());

        return $index;
    }

    private function getCaseAnalysis(
        int $index,
    ): CaseElement {
        while ($index < $this->tokens->count()) {
            $index = $this->getNextSameLevelToken($index);

            if ($this->tokens[$index]->equalsAny([':', ';'])) {
                break;
            }
        }

        return new CaseElement($index);
    }

    private function getCasesEnd(
        int $casesStartIndex,
    ): int {
        if ($this->tokens[$casesStartIndex]->equals('{')) {
            return $this->tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $casesStartIndex);
        }

        $index = $casesStartIndex;

        while ($index < $this->tokens->count()) {
            $index = $this->getNextSameLevelToken($index);

            if ($this->tokens[$index]->isGivenKind(T_ENDSWITCH)) {
                break;
            }
        }

        $afterEndswitchIndex = $this->tokens->getNextMeaningfulToken($index);
        assert(is_int($afterEndswitchIndex));

        return $this->tokens[$afterEndswitchIndex]->equals(';') ? $afterEndswitchIndex : $index;
    }

    private function getCasesStart(
        int $switchIndex,
    ): int {
        $parenthesisStartIndex = $this->tokens->getNextMeaningfulToken($switchIndex);
        assert(is_int($parenthesisStartIndex));
        $parenthesisEndIndex = $this->tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $parenthesisStartIndex);

        $casesStartIndex = $this->tokens->getNextMeaningfulToken($parenthesisEndIndex);
        assert(is_int($casesStartIndex));

        return $casesStartIndex;
    }

    /**
     * @throws Exception
     */
    private function getClosingMatchingToken(
        int $index,
        string $openingToken,
    ): ?int {
        return match ($openingToken) {
            '(' => $this->getClosingParenthesis($index),
            '[' => $this->getClosingBracket($index),
            '{' => $this->getClosingCurlyBracket($index),
            default => throw new Exception(sprintf('Unsupported opening token: %s', $openingToken)),
        };
    }

    private function getNextSameLevelToken(
        ?int $index,
    ): int {
        $index = $this->tokens->getNextMeaningfulToken($index);
        assert(is_int($index));

        if ($this->tokens[$index]->isGivenKind(T_SWITCH)) {
            return $this->getSwitchAnalysis($index)->getCasesEnd();
        }

        $blockType = Tokens::detectBlockType($this->tokens[$index]);

        if (null !== $blockType && $blockType['isStart']) {
            return $this->tokens->findBlockEnd($blockType['type'], $index) + 1;
        }

        return $index;
    }

    private function isConstructor(
        int $index,
        array $element,
    ): bool {
        if ('method' !== $element['type']) {
            return false;
        }

        $functionNameIndex = $this->tokens->getNextMeaningfulToken($index);
        assert(is_int($functionNameIndex));

        return $this->tokens[$functionNameIndex]->equals([T_STRING, '__construct'], false);
    }
}

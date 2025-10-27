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

namespace ValksorDev\PhpCsFixerCustomFixers\Fixer;

use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;
use Valksor\Functions\Preg;
use ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\AbstractFixer;

use function assert;
use function is_int;
use function stripos;
use function substr;

use const T_DECLARE;
use const T_OPEN_TAG;
use const T_WHITESPACE;

final class DeclareAfterOpeningTagFixer extends AbstractFixer
{
    public function applyFix(
        SplFileInfo $file,
        Tokens $tokens,
    ): void {
        if (!$tokens[0]->isGivenKind(T_OPEN_TAG)) {
            return;
        }

        $openingTagTokenContent = $tokens[0]->getContent();

        $declareIndex = $tokens->getNextTokenOfKind(0, [[T_DECLARE]]);
        assert(is_int($declareIndex));

        $openParenthesisIndex = $tokens->getNextMeaningfulToken($declareIndex);
        assert(is_int($openParenthesisIndex));

        $closeParenthesisIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $openParenthesisIndex);

        if (false === stripos($tokens->generatePartialCode($openParenthesisIndex, $closeParenthesisIndex), 'strict_types')) {
            return;
        }

        $tokens[0] = new Token([T_OPEN_TAG, substr($openingTagTokenContent, 0, 5) . ' ']);

        if ($declareIndex <= 2) {
            $tokens->clearRange(1, $declareIndex - 1);

            return;
        }

        $semicolonIndex = $tokens->getNextMeaningfulToken($closeParenthesisIndex);
        assert(is_int($semicolonIndex));

        $tokensToInsert = [];

        for ($index = $declareIndex; $index <= $semicolonIndex; $index++) {
            $tokensToInsert[] = $tokens[$index];
        }

        if ($tokens[1]->isGivenKind(T_WHITESPACE)) {
            $tokens[1] = new Token([T_WHITESPACE, substr($openingTagTokenContent, 5) . $tokens[1]->getContent()]);
        } else {
            $tokensToInsert[] = new Token([T_WHITESPACE, substr($openingTagTokenContent, 5)]);
        }

        static $_helper = null;

        if (null === $_helper) {
            $_helper = new class {
                use Preg\Traits\_Replace;
            };
        }

        if ($tokens[$semicolonIndex + 1]->isGivenKind(T_WHITESPACE)) {
            $content = $_helper->replace('/^(\\R?)(?=\\R)/', '', $tokens[$semicolonIndex + 1]->getContent());

            $tokens->ensureWhitespaceAtIndex($semicolonIndex + 1, 0, $content);
        }

        $tokens->clearRange($declareIndex + 1, $semicolonIndex);
        self::removeWithLinesIfPossible($tokens, $declareIndex);

        $tokens->insertAt(1, $tokensToInsert);
    }

    public function getDocumentation(): string
    {
        return 'Declare statement for strict types must be placed on the same line, after the opening tag.';
    }

    public function getSampleCode(): string
    {
        return "<?php\n\$foo;\ndeclare(strict_types=1);\n\$bar;\n";
    }

    public function isCandidate(
        Tokens $tokens,
    ): bool {
        return $tokens->isTokenKindFound(T_DECLARE);
    }
}

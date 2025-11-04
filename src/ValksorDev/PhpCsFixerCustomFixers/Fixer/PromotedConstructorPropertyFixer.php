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

use PhpCsFixer\DocBlock\DocBlock;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;
use SplFileInfo;
use Valksor\Functions\Preg;
use ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\AbstractFixer;
use ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\Analyzer\Analyzer;
use ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\Analyzer\Element\Constructor;

use function array_key_exists;
use function assert;
use function count;
use function in_array;
use function is_int;
use function krsort;
use function strtolower;
use function substr;

use const T_CLASS;
use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_PRIVATE;
use const T_PROTECTED;
use const T_PUBLIC;
use const T_STRING;
use const T_VAR;
use const T_VARIABLE;
use const T_WHITESPACE;

final class PromotedConstructorPropertyFixer extends AbstractFixer
{
    private array $tokensToInsert;

    public function applyFix(
        SplFileInfo $file,
        Tokens $tokens,
    ): void {
        $constructorAnalyzer = new Analyzer($tokens);
        $this->tokensToInsert = [];

        for ($index = $tokens->count() - 1; $index > 0; $index--) {
            if (!$tokens[$index]->isGivenKind(T_CLASS)) {
                continue;
            }

            $constructorAnalysis = $constructorAnalyzer->findNonAbstractConstructor($index);

            if (null === $constructorAnalysis) {
                continue;
            }

            $this->promoteProperties($tokens, $index, $constructorAnalysis);
        }

        krsort($this->tokensToInsert);

        foreach ($this->tokensToInsert as $index => $tokensToInsert) {
            $tokens->insertAt($index, $tokensToInsert);
        }
    }

    public function getDocumentation(): string
    {
        return 'Constructor properties must be promoted if possible.';
    }

    public function getSampleCode(): string
    {
        return '<?php
            class Foo {
                private string $bar;
                public function __construct(string $bar) {
                    $this->bar = $bar;
                }
            }
        ';
    }

    public function isCandidate(
        Tokens $tokens,
    ): bool {
        return $tokens->isAllTokenKindsFound([T_CLASS, T_VARIABLE]);
    }

    private function getClassProperties(
        Tokens $tokens,
        int $classIndex,
    ): array {
        $properties = [];

        foreach (new TokensAnalyzer($tokens)->getClassyElements() as $index => $element) {
            if ($element['classIndex'] !== $classIndex) {
                continue;
            }

            if ('property' !== $element['type']) {
                continue;
            }

            $properties[substr($element['token']->getContent(), 1)] = $index;
        }

        return $properties;
    }

    private function getPropertyIndex(
        Tokens $tokens,
        array $properties,
        int $assignmentIndex,
    ): ?int {
        $propertyNameIndex = $tokens->getPrevTokenOfKind($assignmentIndex, [[T_STRING]]);
        assert(is_int($propertyNameIndex));

        $propertyName = $tokens[$propertyNameIndex]->getContent();

        foreach ($properties as $name => $index) {
            if ($name !== $propertyName) {
                continue;
            }

            return $index;
        }

        return null;
    }

    private function getTokenOfKindSibling(
        Tokens $tokens,
        int $direction,
        int $index,
        array $tokenKinds,
    ): int {
        $index += $direction;

        while (!$tokens[$index]->equalsAny($tokenKinds)) {
            $blockType = Tokens::detectBlockType($tokens[$index]);

            if (null !== $blockType) {
                if ($blockType['isStart']) {
                    $index = $tokens->findBlockEnd($blockType['type'], $index);
                } else {
                    $index = $tokens->findBlockStart($blockType['type'], $index);
                }
            }

            $index += $direction;
        }

        return $index;
    }

    private function getType(
        Tokens $tokens,
        ?int $variableIndex,
    ): string {
        if (null === $variableIndex) {
            return '';
        }

        $index = $tokens->getPrevTokenOfKind($variableIndex, ['(', ',', [T_PRIVATE], [T_PROTECTED], [T_PUBLIC], [T_VAR], [CT::T_ATTRIBUTE_CLOSE]]);
        assert(is_int($index));

        $index = $tokens->getNextMeaningfulToken($index);
        assert(is_int($index));

        $type = '';

        while ($index < $variableIndex) {
            $type .= $tokens[$index]->getContent();

            $index = $tokens->getNextMeaningfulToken($index);
            assert(is_int($index));
        }

        return $type;
    }

    private function isDoctrineEntity(
        Tokens $tokens,
        int $index,
    ): bool {
        $phpDocIndex = $tokens->getPrevNonWhitespace($index);
        assert(is_int($phpDocIndex));

        if (!$tokens[$phpDocIndex]->isGivenKind(T_DOC_COMMENT)) {
            return false;
        }

        static $_helper = null;

        if (null === $_helper) {
            $_helper = new class {
                use Preg\Traits\_Match;
            };
        }

        foreach (new DocBlock($tokens[$phpDocIndex]->getContent())->getAnnotations() as $annotation) {
            if ($_helper->match('/\\*\\h+(@Document|@Entity|@Mapping\\\\Entity|@ODM\\\\Document|@ORM\\\\Entity|@ORM\\\\Mapping\\\\Entity)/', $annotation->getContent())) {
                return true;
            }
        }

        return false;
    }

    private function isPropertyToPromote(
        Tokens $tokens,
        ?int $propertyIndex,
        bool $isDoctrineEntity,
    ): bool {
        if (null === $propertyIndex) {
            return false;
        }

        if (!$isDoctrineEntity) {
            return true;
        }

        $phpDocIndex = $tokens->getPrevTokenOfKind($propertyIndex, [[T_DOC_COMMENT]]);
        assert(is_int($phpDocIndex));

        $variableIndex = $tokens->getNextTokenOfKind($phpDocIndex, ['{', [T_VARIABLE]]);

        if ($variableIndex !== $propertyIndex) {
            return true;
        }

        $docBlock = new DocBlock($tokens[$phpDocIndex]->getContent());

        return 0 === count($docBlock->getAnnotations());
    }

    private function promoteProperties(
        Tokens $tokens,
        int $classIndex,
        Constructor $constructorAnalysis,
    ): void {
        $isDoctrineEntity = $this->isDoctrineEntity($tokens, $classIndex);
        $properties = $this->getClassProperties($tokens, $classIndex);

        $constructorParameterNames = $constructorAnalysis->getConstructorParameterNames();
        $constructorPromotableParameters = $constructorAnalysis->getConstructorPromotableParameters();
        $constructorPromotableAssignments = $constructorAnalysis->getConstructorPromotableAssignments();

        foreach ($constructorPromotableParameters as $constructorParameterIndex => $constructorParameterName) {
            if (!array_key_exists($constructorParameterName, $constructorPromotableAssignments)) {
                continue;
            }

            $propertyIndex = $this->getPropertyIndex($tokens, $properties, $constructorPromotableAssignments[$constructorParameterName]);

            if (!$this->isPropertyToPromote($tokens, $propertyIndex, $isDoctrineEntity)) {
                continue;
            }

            $propertyType = $this->getType($tokens, $propertyIndex);
            $parameterType = $this->getType($tokens, $constructorParameterIndex);

            if (!$this->typesAllowPromoting($propertyType, $parameterType)) {
                continue;
            }

            $assignedPropertyIndex = $tokens->getPrevTokenOfKind($constructorPromotableAssignments[$constructorParameterName], [[T_STRING]]);
            $oldParameterName = $tokens[$constructorParameterIndex]->getContent();
            $newParameterName = '$' . $tokens[$assignedPropertyIndex]->getContent();

            if ($oldParameterName !== $newParameterName && in_array($newParameterName, $constructorParameterNames, true)) {
                continue;
            }

            $tokensToInsert = $this->removePropertyAndReturnTokensToInsert($tokens, $propertyIndex);

            $this->renameVariable($tokens, $constructorAnalysis->getConstructorIndex(), $oldParameterName, $newParameterName);

            $this->removeAssignment($tokens, $constructorPromotableAssignments[$constructorParameterName]);
            $this->updateParameterSignature(
                $tokens,
                $constructorParameterIndex,
                $tokensToInsert,
                str_starts_with($propertyType, '?'),
            );
        }
    }

    private function removeAssignment(
        Tokens $tokens,
        int $variableAssignmentIndex,
    ): void {
        $thisIndex = $tokens->getPrevTokenOfKind($variableAssignmentIndex, [[T_VARIABLE]]);
        assert(is_int($thisIndex));

        $propertyEndIndex = $tokens->getNextTokenOfKind($variableAssignmentIndex, [';']);
        assert(is_int($propertyEndIndex));

        $tokens->clearRange($thisIndex + 1, $propertyEndIndex);
        self::removeWithLinesIfPossible($tokens, $thisIndex);
    }

    private function removePropertyAndReturnTokensToInsert(
        Tokens $tokens,
        ?int $propertyIndex,
    ): array {
        if (null === $propertyIndex) {
            return [new Token([T_PUBLIC, 'public'])];
        }

        $visibilityIndex = $tokens->getPrevTokenOfKind($propertyIndex, [[T_PRIVATE], [T_PROTECTED], [T_PUBLIC], [T_VAR]]);
        assert(is_int($visibilityIndex));

        $prevPropertyIndex = $this->getTokenOfKindSibling($tokens, -1, $propertyIndex, ['{', '}', ';', ',']);
        $nextPropertyIndex = $this->getTokenOfKindSibling($tokens, 1, $propertyIndex, [';', ',']);

        $removeFrom = $tokens->getTokenNotOfKindSibling($prevPropertyIndex, 1, [[T_WHITESPACE], [T_COMMENT]]);
        assert(is_int($removeFrom));
        $removeTo = $nextPropertyIndex;

        if ($tokens[$prevPropertyIndex]->equals(',')) {
            $removeFrom = $prevPropertyIndex;
            $removeTo = $propertyIndex;
        } elseif ($tokens[$nextPropertyIndex]->equals(',')) {
            $removeFrom = $tokens->getPrevMeaningfulToken($propertyIndex);
            assert(is_int($removeFrom));
            $removeFrom++;
        }

        $tokensToInsert = [];

        for ($index = $removeFrom; $index <= $visibilityIndex - 1; $index++) {
            $tokensToInsert[] = $tokens[$index];
        }

        $visibilityToken = $tokens[$visibilityIndex];

        if ($tokens[$visibilityIndex]->isGivenKind(T_VAR)) {
            $visibilityToken = new Token([T_PUBLIC, 'public']);
        }
        $tokensToInsert[] = $visibilityToken;

        $tokens->clearRange($removeFrom + 1, $removeTo);
        self::removeWithLinesIfPossible($tokens, $removeFrom);

        return $tokensToInsert;
    }

    private function renameVariable(
        Tokens $tokens,
        int $constructorIndex,
        string $oldName,
        string $newName,
    ): void {
        $parenthesesOpenIndex = $tokens->getNextTokenOfKind($constructorIndex, ['(']);
        assert(is_int($parenthesesOpenIndex));
        $parenthesesCloseIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $parenthesesOpenIndex);
        $braceOpenIndex = $tokens->getNextTokenOfKind($parenthesesCloseIndex, ['{']);
        assert(is_int($braceOpenIndex));
        $braceCloseIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $braceOpenIndex);

        for ($index = $parenthesesOpenIndex; $index < $braceCloseIndex; $index++) {
            if ($tokens[$index]->equals([T_VARIABLE, $oldName])) {
                $tokens[$index] = new Token([T_VARIABLE, $newName]);
            }
        }
    }

    private function typesAllowPromoting(
        string $propertyType,
        string $parameterType,
    ): bool {
        if ('' === $propertyType) {
            return true;
        }

        if (str_starts_with($propertyType, '?')) {
            $propertyType = substr($propertyType, 1);
        }

        if (str_starts_with($parameterType, '?')) {
            $parameterType = substr($parameterType, 1);
        }

        return strtolower($propertyType) === strtolower($parameterType);
    }

    private function updateParameterSignature(
        Tokens $tokens,
        int $constructorParameterIndex,
        array $tokensToInsert,
        bool $makeTypeNullable,
    ): void {
        $prevElementIndex = $tokens->getPrevTokenOfKind($constructorParameterIndex, ['(', ',', [CT::T_ATTRIBUTE_CLOSE]]);
        assert(is_int($prevElementIndex));

        $propertyStartIndex = $tokens->getNextMeaningfulToken($prevElementIndex);
        assert(is_int($propertyStartIndex));

        foreach ($tokensToInsert as $index => $token) {
            if ($token->isGivenKind(T_PUBLIC)) {
                $tokensToInsert[$index] = new Token([CT::T_CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC, $token->getContent()]);
            } elseif ($token->isGivenKind(T_PROTECTED)) {
                $tokensToInsert[$index] = new Token([CT::T_CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED, $token->getContent()]);
            } elseif ($token->isGivenKind(T_PRIVATE)) {
                $tokensToInsert[$index] = new Token([CT::T_CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE, $token->getContent()]);
            }
        }
        $tokensToInsert[] = new Token([T_WHITESPACE, ' ']);

        if ($makeTypeNullable && !$tokens[$propertyStartIndex]->isGivenKind(CT::T_NULLABLE_TYPE)) {
            $tokensToInsert[] = new Token([CT::T_NULLABLE_TYPE, '?']);
        }

        $this->tokensToInsert[$propertyStartIndex] = $tokensToInsert;
    }
}

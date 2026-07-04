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

namespace ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer;

use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\WhitespacesFixerConfig;
use SplFileInfo;
use Valksor\Functions\Preg;
use ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\Analyzer\Analyzer;

use function array_filter;
use function array_map;
use function array_pop;
use function assert;
use function count;
use function explode;
use function is_array;
use function is_int;
use function sprintf;
use function strlen;
use function substr;

use const T_CLASS;
use const T_EXTENDS;
use const T_IMPLEMENTS;
use const T_NS_SEPARATOR;
use const T_OPEN_TAG;
use const T_STRING;
use const T_USE;
use const T_WHITESPACE;

/**
 * @internal
 */
abstract class AbstractFixer implements FixerInterface, WhitespacesAwareFixerInterface
{
    public const string PREFIX = 'ValksorPhpCsFixerCustomFixers';

    protected WhitespacesFixerConfig $whitespacesConfig;

    abstract public function getDocumentation(): string;

    abstract public function getSampleCode(): string;

    final public function fix(
        SplFileInfo $file,
        Tokens $tokens,
    ): void {
        if ($tokens->count() > 0 && $this->isCandidate($tokens) && $this->supports($file)) {
            $this->applyFix($file, $tokens);
        }
    }

    final public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            $this->getDocumentation(),
            array_map(
                fn (?array $configutation = null) => new CodeSample($this->getSampleCode(), $configutation),
                [[]],
            ),
        );
    }

    final public function getName(): string
    {
        return self::getNameForClass(static::class);
    }

    public function getPriority(): int
    {
        return 30;
    }

    public function isCandidate(
        Tokens $tokens,
    ): bool {
        return true;
    }

    public function isRisky(): bool
    {
        return false;
    }

    public function setWhitespacesConfig(
        WhitespacesFixerConfig $config,
    ): void {
        $this->whitespacesConfig = $config;
    }

    final public function supports(
        SplFileInfo $file,
    ): bool {
        return true;
    }

    final public static function getNameForClass(
        string $class,
    ): string {
        $parts = explode('\\', $class);
        $name = substr($parts[count($parts) - 1], 0, -strlen('Fixer'));

        return sprintf('%s/%s', self::PREFIX, self::snakeCaseFromCamelCase($name));
    }

    public static function removeWithLinesIfPossible(
        Tokens $tokens,
        int $index,
    ): void {
        if (self::isTokenOnlyMeaningfulInLine($tokens, $index)) {
            $prev = $tokens->getNonEmptySibling($index, -1);
            assert(is_int($prev));
            $wasNewlineRemoved = self::handleWhitespaceBefore($tokens, $prev);

            $next = $tokens->getNonEmptySibling($index, 1);

            if (null !== $next) {
                self::handleWhitespaceAfter($tokens, $next, $wasNewlineRemoved);
            }
        }

        $tokens->clearTokenAndMergeSurroundingWhitespace($index);
    }

    abstract protected function applyFix(
        SplFileInfo $file,
        Tokens $tokens,
    ): void;

    protected function analyze(
        Tokens $tokens,
    ): Analyzer {
        return new Analyzer($tokens);
    }

    /**
     * @param list<string>|string $fqcn
     */
    protected function extendsClass(
        Tokens $tokens,
        array|string $fqcn,
    ): bool {
        $fqcn = is_array($fqcn) ? $fqcn : explode('\\', $fqcn);

        return $this->hasUseStatements($tokens, $fqcn)
            && null !== $tokens->findSequence([
                [T_CLASS],
                [T_STRING],
                [T_EXTENDS],
                [T_STRING, array_pop($fqcn)],
            ]);
    }

    /**
     * @return array<int, Token>
     */
    protected function getComments(
        Tokens $tokens,
    ): array {
        return array_filter($tokens->toArray(), static fn ($token) => $token->isComment());
    }

    /**
     * @param list<string>|string $fqcn
     *
     * @return array<int, Token>|null
     */
    protected function getUseStatements(
        Tokens $tokens,
        array|string $fqcn,
    ): ?array {
        $fqcnArray = is_array($fqcn) ? $fqcn : explode('\\', $fqcn);
        $sequence = [[T_USE]];

        foreach ($fqcnArray as $component) {
            $sequence[] = [T_STRING, $component];
            $sequence[] = [T_NS_SEPARATOR];
        }
        $sequence[count($sequence) - 1] = ';';

        return $tokens->findSequence($sequence);
    }

    /**
     * @param list<string>|string $fqcn
     */
    protected function hasUseStatements(
        Tokens $tokens,
        array|string $fqcn,
    ): bool {
        return null !== $this->getUseStatements($tokens, $fqcn);
    }

    /**
     * @param list<string>|string $fqcn
     */
    protected function implementsInterface(
        Tokens $tokens,
        array|string $fqcn,
    ): bool {
        $fqcn = is_array($fqcn) ? $fqcn : explode('\\', $fqcn);

        return $this->hasUseStatements($tokens, $fqcn)
            && null !== $tokens->findSequence([
                [T_CLASS],
                [T_STRING],
                [T_IMPLEMENTS],
                [T_STRING, array_pop($fqcn)],
            ]);
    }

    protected static function handleWhitespaceAfter(
        Tokens $tokens,
        int $index,
        bool $wasNewlineRemoved,
    ): void {
        $pattern = $wasNewlineRemoved ? '/^\\h+/' : '/^\\h*\\R/';

        $newContent = self::preg()->replace($pattern, '', $tokens[$index]->getContent());
        $tokens->ensureWhitespaceAtIndex($index, 0, $newContent);
    }

    protected static function handleWhitespaceBefore(
        Tokens $tokens,
        int $index,
    ): bool {
        if (!$tokens[$index]->isGivenKind(T_WHITESPACE)) {
            return false;
        }

        $withoutTrailingSpaces = self::preg()->replace('/\\h+$/', '', $tokens[$index]->getContent());
        $withoutNewline = self::preg()->replace('/\\R$/', '', $withoutTrailingSpaces, 1);
        $tokens->ensureWhitespaceAtIndex($index, 0, $withoutNewline);

        return $withoutTrailingSpaces !== $withoutNewline;
    }

    protected static function hasMeaningTokenInLineAfter(
        Tokens $tokens,
        int $index,
    ): bool {
        $next = $tokens->getNonEmptySibling($index, 1);

        return null !== $next && (!$tokens[$next]->isGivenKind(T_WHITESPACE) || !self::preg()->match('/\\R/', $tokens[$next]->getContent()));
    }

    protected static function hasMeaningTokenInLineBefore(
        Tokens $tokens,
        int $index,
    ): bool {
        $prev = $tokens->getNonEmptySibling($index, -1);
        assert(is_int($prev));

        if (!$tokens[$prev]->isGivenKind([T_OPEN_TAG, T_WHITESPACE])) {
            return true;
        }

        if ($tokens[$prev]->isGivenKind(T_OPEN_TAG) && !self::preg()->match('/\\R$/', $tokens[$prev]->getContent())) {
            return true;
        }

        if (!self::preg()->match('/\\R/', $tokens[$prev]->getContent())) {
            $prevPrev = $tokens->getNonEmptySibling($prev, -1);
            assert(is_int($prevPrev));

            if (!$tokens[$prevPrev]->isGivenKind(T_OPEN_TAG) || !self::preg()->match('/\\R$/', $tokens[$prevPrev]->getContent())) {
                return true;
            }
        }

        return false;
    }

    protected static function isTokenOnlyMeaningfulInLine(
        Tokens $tokens,
        int $index,
    ): bool {
        return !self::hasMeaningTokenInLineBefore($tokens, $index) && !self::hasMeaningTokenInLineAfter($tokens, $index);
    }

    /**
     * Shared UTF-8-safe preg helper (match/replace) reused across the fixers.
     */
    protected static function preg(): Preg\Functions
    {
        static $preg = null;

        return $preg ??= new Preg\Functions();
    }

    protected static function snakeCaseFromCamelCase(
        string $string,
        string $separator = '_',
    ): string {
        return mb_strtolower(string: self::preg()->replace(pattern: '#(?!^)[[:upper:]]+#', replacement: $separator . '$0', subject: $string));
    }
}

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
use PhpCsFixer\WhitespacesFixerConfig;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\AbstractFixer;

abstract class AbstractFixerTestCase extends TestCase
{
    protected function applyFixer(
        AbstractFixer $fixer,
        string $input,
    ): string {
        $tokens = Tokens::fromCode($input);

        $fixer->setWhitespacesConfig(new WhitespacesFixerConfig());
        $fixer->fix(new SplFileInfo(__FILE__), $tokens);

        $tokens->clearEmptyTokens();

        return $tokens->generateCode();
    }

    protected function assertFixerCodeSame(
        AbstractFixer $fixer,
        string $expected,
        string $input,
    ): void {
        self::assertSame($expected, $this->applyFixer($fixer, $input));
    }

    protected function assertFixerDoesNotChangeCode(
        AbstractFixer $fixer,
        string $code,
    ): void {
        $this->assertFixerCodeSame($fixer, $code, $code);
    }
}

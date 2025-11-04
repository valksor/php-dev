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

use ValksorDev\PhpCsFixerCustomFixers\Fixer\LineBreakBetweenMethodArgumentsFixer;

final class LineBreakBetweenMethodArgumentsFixerTest extends AbstractFixerTestCase
{
    public function testMergesEmptyArgumentList(): void
    {
        $input = <<<'PHP'
            <?php
            final class Example
            {
                public function run(
                ) {
                }
            }
            PHP;

        $expected = <<<'PHP'
            <?php
            final class Example
            {
                public function run()
                {
                }
            }
            PHP;

        $this->assertFixerCodeSame(new LineBreakBetweenMethodArgumentsFixer(), $expected . "\n", $input . "\n");
    }

    public function testSplitsArgumentsToSeparateLines(): void
    {
        $input = <<<'PHP'
            <?php
            final class Example
            {
                public function run(string $first, int $second, bool $third = true) {
                    return $second;
                }
            }
            PHP;

        $expected = <<<'PHP'
            <?php
            final class Example
            {
                public function run(
                    string $first,
                    int $second,
                    bool $third = true
                ) {
                    return $second;
                }
            }
            PHP;

        $this->assertFixerCodeSame(new LineBreakBetweenMethodArgumentsFixer(), $expected . "\n", $input . "\n");
    }
}

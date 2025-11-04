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

use ValksorDev\PhpCsFixerCustomFixers\Fixer\LineBreakBetweenStatementsFixer;

final class LineBreakBetweenStatementsFixerTest extends AbstractFixerTestCase
{
    public function testAddsEmptyLineBetweenHandledStatements(): void
    {
        $input = <<<'PHP'
            <?php
            final class Example
            {
                public function run(): void
                {
                    foreach ($items as $item) {
                        process($item);
                    }
                    if ($items === []) {
                        return;
                    }
                    while (true) {
                        break;
                    }
                }
            }
            PHP;

        $expected = <<<'PHP'
            <?php
            final class Example
            {
                public function run(): void
                {
                    foreach ($items as $item) {
                        process($item);
                    }

                    if ($items === []) {
                        return;
                    }

                    while (true) {
                        break;
                    }
                }
            }
            PHP;

        $this->assertFixerCodeSame(new LineBreakBetweenStatementsFixer(), $expected . "\n", $input . "\n");
    }
}

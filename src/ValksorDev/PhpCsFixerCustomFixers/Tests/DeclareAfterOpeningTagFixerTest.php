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

use ValksorDev\PhpCsFixerCustomFixers\Fixer\DeclareAfterOpeningTagFixer;

final class DeclareAfterOpeningTagFixerTest extends AbstractFixerTestCase
{
    public function testMovesDeclareToOpeningTagLine(): void
    {
        $input = <<<'PHP'
            <?php

            declare(strict_types=1);

            $value = 1;
            PHP;

        $expected = <<<'PHP'
            <?php declare(strict_types=1);

            $value = 1;
            PHP;

        $this->assertFixerCodeSame(new DeclareAfterOpeningTagFixer(), $expected . "\n", $input . "\n");
    }

    public function testSkipsWhenDeclareAlreadyInline(): void
    {
        $code = <<<'PHP'
            <?php declare(strict_types=1);

            $value = 1;
            PHP;

        $this->assertFixerDoesNotChangeCode(new DeclareAfterOpeningTagFixer(), $code . "\n");
    }

    public function testSkipsWhenDeclareIsNotStrictTypes(): void
    {
        $code = <<<'PHP'
            <?php
            declare(ticks=1);
            PHP;

        $this->assertFixerDoesNotChangeCode(new DeclareAfterOpeningTagFixer(), $code . "\n");
    }
}

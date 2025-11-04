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

use ValksorDev\PhpCsFixerCustomFixers\Fixer\NoUselessDirnameCallFixer;

final class NoUselessDirnameCallFixerTest extends AbstractFixerTestCase
{
    public function testRewritesDirnameCalls(): void
    {
        $input = <<<'PHP'
            <?php
            require dirname(__DIR__) . '/vendor/autoload.php';
            require \dirname(__DIR__, 2) . "/vendor/autoload.php";
            PHP;

        $expected = <<<'PHP'
            <?php
            require __DIR__ . '/../vendor/autoload.php';
            require __DIR__  . "/../../vendor/autoload.php";
            PHP;

        $this->assertFixerCodeSame(new NoUselessDirnameCallFixer(), $expected . "\n", $input . "\n");
    }
}

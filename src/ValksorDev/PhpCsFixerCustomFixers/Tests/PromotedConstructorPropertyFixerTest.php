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

use ValksorDev\PhpCsFixerCustomFixers\Fixer\PromotedConstructorPropertyFixer;

final class PromotedConstructorPropertyFixerTest extends AbstractFixerTestCase
{
    public function testPromotesConstructorProperty(): void
    {
        $input = <<<'PHP'
            <?php
            final class Example
            {
                private string $name;

                public function __construct(string $name)
                {
                    $this->name = $name;
                }
            }
            PHP;

        $expected = <<<'PHP'
            <?php
            final class Example
            {

                public function __construct(private string $name)
                {
                }
            }
            PHP;

        $this->assertFixerCodeSame(new PromotedConstructorPropertyFixer(), $expected . "\n", $input . "\n");
    }
}

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
    public function testDoesNotChangeAlreadyPromotedProperty(): void
    {
        $code = <<<'PHP'
            <?php
            final class Example
            {
                public function __construct(private string $name)
                {
                }
            }
            PHP;

        $this->assertFixerDoesNotChangeCode(new PromotedConstructorPropertyFixer(), $code . "\n");
    }

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

    public function testPromotesMultipleConstructorProperties(): void
    {
        $input = <<<'PHP'
            <?php
            final class Example
            {
                private string $a;
                private int $b;

                public function __construct(string $a, int $b)
                {
                    $this->a = $a;
                    $this->b = $b;
                }
            }
            PHP;

        $expected = <<<'PHP'
            <?php
            final class Example
            {

                public function __construct(private string $a, private int $b)
                {
                }
            }
            PHP;

        $this->assertFixerCodeSame(new PromotedConstructorPropertyFixer(), $expected . "\n", $input . "\n");
    }

    public function testPromotesNullableConstructorProperty(): void
    {
        $input = <<<'PHP'
            <?php
            final class Example
            {
                private ?string $name;

                public function __construct(?string $name = null)
                {
                    $this->name = $name;
                }
            }
            PHP;

        $expected = <<<'PHP'
            <?php
            final class Example
            {

                public function __construct(private ?string $name = null)
                {
                }
            }
            PHP;

        $this->assertFixerCodeSame(new PromotedConstructorPropertyFixer(), $expected . "\n", $input . "\n");
    }
}

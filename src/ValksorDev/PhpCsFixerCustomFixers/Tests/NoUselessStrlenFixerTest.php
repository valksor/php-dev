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

use PHPUnit\Framework\Attributes\DataProvider;
use ValksorDev\PhpCsFixerCustomFixers\Fixer\NoUselessStrlenFixer;

final class NoUselessStrlenFixerTest extends AbstractFixerTestCase
{
    #[DataProvider('provideConversions')]
    public function testConvertsStrlenComparisons(
        string $expected,
        string $input,
    ): void {
        $fixer = new NoUselessStrlenFixer();

        $this->assertFixerCodeSame($fixer, $expected . "\n", $input . "\n");
    }

    public static function provideConversions(): iterable
    {
        yield 'comparison on right' => [
            <<<'PHP'
                <?php
                $isEmpty = $value === '';
                PHP,
            <<<'PHP'
                <?php
                $isEmpty = strlen($value) === 0;
                PHP,
        ];

        yield 'comparison on left' => [
            <<<'PHP'
                <?php
                $isNotEmpty = '' !== $value;
                PHP,
            <<<'PHP'
                <?php
                $isNotEmpty = 0 < strlen($value);
                PHP,
        ];

        yield 'multibyte function' => [
            <<<'PHP'
                <?php
                if ($value !== '') {
                    return;
                }
                PHP,
            <<<'PHP'
                <?php
                if (mb_strlen($value) > 0) {
                    return;
                }
                PHP,
        ];
    }
}

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

use DirectoryIterator;
use PhpCsFixer\Fixer\FixerInterface;
use PHPUnit\Framework\TestCase;
use ValksorDev\PhpCsFixerCustomFixers\Fixers;
use ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\AbstractFixer;

final class FixersTest extends TestCase
{
    public function testGetFixersReturnsNamesIndexedByFixerName(): void
    {
        $fixerInstances = iterator_to_array(new Fixers(), preserve_keys: false);

        $expectedNames = [];

        foreach ($fixerInstances as $fixer) {
            $expectedNames[] = AbstractFixer::getNameForClass($fixer::class);
        }

        sort($expectedNames);

        $fixers = Fixers::getFixers();

        self::assertNotEmpty($fixers, 'No fixers returned from Fixers::getFixers().');

        foreach ($fixers as $isEnabled) {
            self::assertTrue($isEnabled, 'Fixers::getFixers() must mark fixers as available.');
        }

        $actualNames = array_keys($fixers);
        sort($actualNames);

        self::assertSame($expectedNames, $actualNames);
    }

    public function testIteratorReturnsCustomFixerInstances(): void
    {
        $fixers = iterator_to_array(new Fixers(), preserve_keys: false);

        self::assertNotEmpty($fixers, 'No fixers discovered via iterator.');

        $actualClasses = [];

        foreach ($fixers as $fixer) {
            self::assertInstanceOf(FixerInterface::class, $fixer);
            $actualClasses[] = $fixer::class;
        }

        sort($actualClasses);

        $expectedClasses = [];

        foreach (new DirectoryIterator(__DIR__ . '/../Fixer') as $fileInfo) {
            if (!$fileInfo->isFile() || 'php' !== $fileInfo->getExtension()) {
                continue;
            }

            $expectedClasses[] = 'ValksorDev\\PhpCsFixerCustomFixers\\Fixer\\' . $fileInfo->getBasename('.php');
        }

        sort($expectedClasses);

        self::assertSame($expectedClasses, $actualClasses);
    }
}

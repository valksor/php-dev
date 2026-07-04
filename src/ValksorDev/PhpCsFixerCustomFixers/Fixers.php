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

namespace ValksorDev\PhpCsFixerCustomFixers;

use DirectoryIterator;
use Generator;
use IteratorAggregate;
use PhpCsFixer\Fixer\FixerInterface;
use ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\AbstractFixer;

use function assert;
use function sort;

/**
 * @implements IteratorAggregate<int, FixerInterface>
 */
final class Fixers implements IteratorAggregate
{
    public function getIterator(): Generator
    {
        foreach (self::discoverFixerClasses() as $className) {
            $fixer = new $className();
            assert($fixer instanceof FixerInterface);

            yield $fixer;
        }
    }

    /**
     * Names of every custom fixer, indexed by name, ready to spread into a
     * php-cs-fixer rule set.
     *
     * @return array<string, true>
     */
    public static function getFixers(): array
    {
        $fixers = [];

        foreach (self::discoverFixerClasses() as $className) {
            $fixers[AbstractFixer::getNameForClass($className)] = true;
        }

        return $fixers;
    }

    /**
     * Discover the fixer classes shipped in the Fixer/ directory.
     *
     * @return list<class-string>
     */
    private static function discoverFixerClasses(): array
    {
        $classNames = [];

        foreach (new DirectoryIterator(__DIR__ . '/Fixer') as $fileInfo) {
            if ($fileInfo->isDot() || 'php' !== $fileInfo->getExtension()) {
                continue;
            }

            $classNames[] = __NAMESPACE__ . '\\Fixer\\' . $fileInfo->getBasename('.php');
        }

        sort($classNames);

        return $classNames;
    }
}

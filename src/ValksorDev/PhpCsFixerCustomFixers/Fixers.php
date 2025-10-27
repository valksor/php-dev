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
use Symfony\Component\Finder\Finder;
use Valksor\Functions\Preg;
use ValksorDev\PhpCsFixerCustomFixers\PhpCsFixer\AbstractFixer;

use function assert;
use function class_exists;
use function file_get_contents;
use function in_array;
use function sort;

final class Fixers implements IteratorAggregate
{
    public function getIterator(): Generator
    {
        $classNames = [];

        foreach (new DirectoryIterator(__DIR__ . '/Fixer') as $fileInfo) {
            $fileName = $fileInfo->getBasename('.php');

            if (in_array($fileName, ['.', '..', ], true)) {
                continue;
            }
            $classNames[] = __NAMESPACE__ . '\\Fixer\\' . $fileName;
        }

        sort($classNames);

        foreach ($classNames as $className) {
            $fixer = new $className();
            assert($fixer instanceof FixerInterface);

            yield $fixer;
        }
    }

    public static function getFixers(): array
    {
        $finder = new Finder();
        $finder->files()->in(__DIR__ . '/Fixer/')->name('*.php');
        $files = [];

        static $_helper = null;

        if (null === $_helper) {
            $_helper = new class {
                use Preg\Traits\_Match;
            };
        }

        $classMatches = $namespaceMatches = ['', ''];

        foreach ($finder as $file) {
            $fileContents = file_get_contents($file->getRealPath());

            if ($_helper->match('/namespace\s+(.+?);/', $fileContents, $namespaceMatches)
                && $_helper->match('/class\s+(\w+)/', $fileContents, $classMatches)) {
                $className = $classMatches[1];
                $fullClassName = $namespaceMatches[1] . '\\' . $className;

                if (class_exists($fullClassName)) {
                    $files[] = $fullClassName;
                }
            }
        }

        $fixers = [];

        foreach ($files as $fixer) {
            $fixers[AbstractFixer::getNameForClass($fixer)] = true;
        }

        return $fixers;
    }
}

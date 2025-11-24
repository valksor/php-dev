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

namespace ValksorDev\Build\Tests;

use PHPUnit\Framework\TestCase;
use Valksor\Bundle\Service\PathFilter;

final class PathFilterTest extends TestCase
{
    public function testDirectoryFiltering(): void
    {
        $filter = PathFilter::createDefault('/test/project');

        self::assertTrue($filter->shouldIgnoreDirectory('node_modules'));
        self::assertTrue($filter->shouldIgnoreDirectory('NODE_MODULES'));
        self::assertFalse($filter->shouldIgnoreDirectory('src'));
    }

    public function testPathFilteringRules(): void
    {
        $filter = PathFilter::createDefault('/test/project');

        self::assertFalse($filter->shouldIgnorePath(null));

        // Use backward compatibility methods instead of reflection
        $ignoredFilenames = $filter->getIgnoredFilenames();
        self::assertContains('.gitignore', $ignoredFilenames);

        $ignoredExtensions = $filter->getIgnoredExtensions();
        self::assertContains('.md', $ignoredExtensions);

        $basename = strtolower(pathinfo('app/.gitignore', PATHINFO_BASENAME));
        self::assertSame('.gitignore', $basename);
        self::assertContains($basename, $ignoredFilenames);

        $result = $filter->shouldIgnorePath('app/.gitignore');
        self::assertTrue($result, 'shouldIgnorePath returned false for .gitignore');
        self::assertTrue($filter->shouldIgnorePath('README.md'));
        self::assertTrue($filter->shouldIgnorePath('src/node_modules/package/index.js')); // Should be ignored by **/node_modules/** pattern

        self::assertFalse($filter->shouldIgnorePath('src/Controller/HomeController.php'));
        self::assertFalse($filter->shouldIgnorePath('resources/styles/app.css'));
        self::assertFalse($filter->shouldIgnorePath('docs/guide.txt'));
    }
}

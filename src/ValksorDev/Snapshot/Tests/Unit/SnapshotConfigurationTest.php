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

namespace ValksorDev\Snapshot\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ValksorDev\Snapshot\DependencyInjection\SnapshotConfiguration;

/**
 * Tests for SnapshotConfiguration class.
 *
 * Tests the configuration validation and default values.
 */
final class SnapshotConfigurationTest extends TestCase
{
    private SnapshotConfiguration $configuration;

    public function testConfigurationMethodsExist(): void
    {
        // Test that the required methods exist
        $this->assertTrue(method_exists($this->configuration, 'addSection'));
        $this->assertTrue(method_exists($this->configuration, 'registerPreConfiguration'));
        $this->assertTrue(method_exists($this->configuration, 'getDefaults'));
    }

    public function testDefaultConfigurationValues(): void
    {
        $expectedDefaults = SnapshotConfiguration::getDefaults();

        $this->assertIsArray($expectedDefaults);
        $this->assertArrayHasKey('options', $expectedDefaults);

        $options = $expectedDefaults['options'];
        $this->assertSame(500, $options['max_files']);
        $this->assertSame(1000, $options['max_lines']);
        $this->assertSame(1048576, $options['max_file_size']); // 1MB in bytes
        $this->assertFalse($options['include_vendors']);
        $this->assertFalse($options['include_hidden']);
        $this->assertFalse($options['use_gitignore']);
        $this->assertIsArray($options['exclude']);
        $this->assertNotEmpty($options['exclude']);
    }

    public function testDefaultExcludePatterns(): void
    {
        $excludePatterns = SnapshotConfiguration::getDefaults()['options']['exclude'];

        // Check for common exclude patterns
        $this->assertContains('tests', $excludePatterns);
        $this->assertContains('Tests', $excludePatterns);
        $this->assertContains('vendor', $excludePatterns);
        $this->assertContains('node_modules', $excludePatterns);
        $this->assertContains('.git', $excludePatterns);
        $this->assertContains('.coverage', $excludePatterns);
        $this->assertContains('coverage', $excludePatterns);
        $this->assertContains('build', $excludePatterns);
        $this->assertContains('dist', $excludePatterns);
    }

    public function testDefaultValuesAreSensible(): void
    {
        $defaults = SnapshotConfiguration::getDefaults();
        $options = $defaults['options'];

        // Test that default values are sensible
        $this->assertGreaterThan(0, $options['max_files']);
        $this->assertGreaterThan(0, $options['max_lines']);
        $this->assertGreaterThan(0, $options['max_file_size']);
        $this->assertIsBool($options['include_vendors']);
        $this->assertIsBool($options['include_hidden']);
        $this->assertIsBool($options['use_gitignore']);
    }

    public function testGetDefaultsMethod(): void
    {
        $defaults = SnapshotConfiguration::getDefaults();

        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('options', $defaults);
        $this->assertArrayHasKey('max_files', $defaults['options']);
        $this->assertArrayHasKey('max_file_size', $defaults['options']);
        $this->assertArrayHasKey('max_lines', $defaults['options']);
        $this->assertArrayHasKey('include_vendors', $defaults['options']);
        $this->assertArrayHasKey('include_hidden', $defaults['options']);
        $this->assertArrayHasKey('use_gitignore', $defaults['options']);
        $this->assertArrayHasKey('exclude', $defaults['options']);
        $this->assertIsArray($defaults['options']['exclude']);
    }

    public function testGetDefaultsMethodReturnsStaticArray(): void
    {
        $defaults1 = SnapshotConfiguration::getDefaults();
        $defaults2 = SnapshotConfiguration::getDefaults();

        // Should return the same structure each time
        $this->assertSame($defaults1, $defaults2);
    }

    protected function setUp(): void
    {
        $this->configuration = new SnapshotConfiguration();
    }
}

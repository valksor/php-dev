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

namespace ValksorDev\Snapshot\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Valksor\Bundle\DependencyInjection\AbstractDependencyConfiguration;
use Valksor\Bundle\ValksorBundle;

use function sprintf;

/**
 * Snapshot command dependency injection configuration.
 *
 * This class defines the configuration schema for the Snapshot command,
 * enabling proper dependency injection and service registration within
 * the Valksor framework.
 *
 * Configuration Structure:
 * - enabled: Master switch to enable/disable snapshot functionality
 * - options: Default options for snapshot generation
 *
 * Integration:
 * This configuration integrates with the ValksorBundle's dependency injection
 * system to automatically register Snapshot services when enabled.
 */
class SnapshotConfiguration extends AbstractDependencyConfiguration
{
    public function addSection(
        ArrayNodeDefinition $rootNode,
        callable $enableIfStandalone,
        string $component,
    ): void {
        $rootNode
            ->children()
                ->arrayNode($component)
                    ->{$enableIfStandalone(sprintf('%s/%s', ValksorBundle::VALKSOR, $component), self::class)}()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Enable or disable the snapshot command')
                            ->example('true')
                            ->defaultFalse()
                        ->end()
                        ->arrayNode('options')
                            ->info('Default options for snapshot generation')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->integerNode('max_files')
                                    ->info('Maximum number of files to process (0 for unlimited)')
                                    ->example('500')
                                    ->defaultValue(500)
                                    ->min(0)
                                ->end()
                                ->integerNode('max_file_size')
                                    ->info('Maximum file size in bytes (0 for unlimited)')
                                    ->example('1048576')
                                    ->defaultValue(1048576) // 1MB
                                    ->min(0)
                                ->end()
                                ->integerNode('max_lines')
                                    ->info('Maximum lines per file (0 for unlimited)')
                                    ->example('1000')
                                    ->defaultValue(1000)
                                    ->min(0)
                                ->end()
                                ->booleanNode('include_vendors')
                                    ->info('Include vendor directories by default')
                                    ->example('false')
                                    ->defaultFalse()
                                ->end()
                                ->booleanNode('include_hidden')
                                    ->info('Include hidden files and directories by default')
                                    ->example('false')
                                    ->defaultFalse()
                                ->end()
                                ->booleanNode('use_gitignore')
                                    ->info('Use .gitignore patterns by default')
                                    ->example('true')
                                    ->defaultTrue()
                                ->end()
                                ->arrayNode('exclude')
                                    ->info('Default exclusion patterns for snapshot generation')
                                    ->example([
                                        'tests', 'Tests', 'coverage', '.coverage', 'build', 'dist',
                                        'node_modules', 'vendor', '.git', '.idea', '.phpunit.cache',
                                        '**/*.log', '**/.DS_Store',
                                    ])
                                    ->defaultValue(self::getDefaults()['options']['exclude'])
                                    ->scalarPrototype()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    public function registerPreConfiguration(
        ContainerConfigurator $container,
        ContainerBuilder $builder,
        string $component,
    ): void {
        // No pre-configuration needed for Snapshot
    }

    public static function getDefaults(): array
    {
        return [
            'options' => [
                'max_files' => 500,
                'max_file_size' => 1048576, // 1MB
                'max_lines' => 1000,
                'include_vendors' => false,
                'include_hidden' => false,
                'use_gitignore' => false,
                'exclude' => [
                    // Test and build directories (anywhere in project)
                    'tests', 'Tests', 'coverage', '.coverage', 'build', 'dist', 'out',

                    // Cache and temporary directories (anywhere in project)
                    '.phpunit.cache', 'cache', 'tmp', 'temp',

                    // Large dependency directories (anywhere in project)
                    'node_modules', 'vendor',

                    // Development and IDE directories (anywhere in project)
                    '.git', '.idea', '.vscode', '.webpack-cache', '.cache', '.env', '.env.local', '.gitignore', '.gitkeep',

                    // Lock files and build artifacts
                    '.neon', '.lock', 'LICENSE', '.md', 'reference.php', '.mcp',

                    // File patterns by extension/type
                    '**/*.log', '**/.DS_Store', '**/*.lock',
                ],
            ],
        ];
    }
}

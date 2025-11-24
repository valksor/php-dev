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

namespace ValksorDev\Snapshot\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Valksor\Bundle\Command\AbstractCommand;
use ValksorDev\Snapshot\Service\SnapshotService;

use function explode;
use function getcwd;
use function is_array;
use function is_dir;
use function realpath;
use function str_contains;
use function trim;

/**
 * Console command for generating MCP (Markdown Context Pack) snapshots.
 *
 * This command creates AI-optimized project documentation snapshots that include
 * project structure, file contents organized by language, and comprehensive
 * statistics. The output format is specifically designed for consumption by
 * AI assistants and code analysis tools.
 *
 * Key Features:
 * - Multi-path scanning with flexible configuration
 * - Intelligent file filtering with binary detection
 * - Gitignore integration for smart filtering
 * - Configurable limits for file size, count, and lines
 * - Extension allowlisting and custom ignore patterns
 * - MCP format output optimized for AI consumption
 *
 * Use Cases:
 * - AI code analysis and review
 * - Project documentation generation
 * - Code base summarization for context
 * - Knowledge base creation for assistants
 */
#[AsCommand(
    name: 'valksor:snapshot',
    description: 'Generate project snapshots in MCP format for AI consumption.',
)]
final class SnapshotGenerateCommand extends AbstractCommand
{
    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly SnapshotService $snapshotService,
    ) {
        parent::__construct($parameterBag);
    }

    /**
     * Execute the snapshot generation command.
     *
     * Processes command arguments and options, configures the snapshot service,
     * and triggers the snapshot generation with proper error handling.
     */
    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = $this->createSymfonyStyle($input, $output);
        $this->snapshotService->setIo($io);

        // Validate and prepare paths
        $paths = $this->preparePaths($input, $io);

        if (empty($paths)) {
            return 1; // Error already shown in preparePaths
        }

        // Build configuration
        $config = $this->buildConfig($input, $paths);

        // Generate snapshot
        return $this->snapshotService->start($config);
    }

    /**
     * Configure command arguments and options.
     *
     * Sets up comprehensive configuration options for controlling snapshot
     * generation including paths, filtering, output format, and limits.
     */
    protected function configure(): void
    {
        $this
            ->addArgument(
                'paths',
                InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'Path(s) to scan (can specify multiple paths)',
                [getcwd()],
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output file name (auto-generated with timestamp if not specified)',
            )
            ->addOption(
                'no-gitignore',
                null,
                InputOption::VALUE_NONE,
                'Ignore .gitignore patterns and process all files',
            )
            ->addOption(
                'include-vendors',
                null,
                InputOption::VALUE_NONE,
                'Include vendor directories (node_modules, vendor, etc.)',
            )
            ->addOption(
                'include-hidden',
                null,
                InputOption::VALUE_NONE,
                'Include hidden files and directories (starting with .)',
            )
            ->addOption(
                'max-files',
                null,
                InputOption::VALUE_REQUIRED,
                'Maximum number of files to process (0 for unlimited)',
                '500',
            )
            ->addOption(
                'max-size',
                null,
                InputOption::VALUE_REQUIRED,
                'Maximum file size in KB (0 for unlimited)',
                '1024',
            )
            ->addOption(
                'max-lines',
                null,
                InputOption::VALUE_REQUIRED,
                'Maximum lines per file (0 for unlimited)',
                '1000',
            )
            ->addOption(
                'extensions',
                'ext',
                InputOption::VALUE_REQUIRED,
                'Only include files with these extensions (comma-separated, no dots)',
            )
            ->addOption(
                'ignore',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Files/directories/extensions to ignore (can specify multiple times)',
            )
            ->setHelp(
                <<<'EOF'
                    The <info>%command.name%</info> command generates project snapshots in MCP (Markdown Context Pack) format optimized for AI consumption.

                    <info>Usage examples:</info>

                    Generate snapshot of current directory:
                      <info>php %command.full_name%</info>

                    Generate with custom output file:
                      <info>php %command.full_name% --output=project-snapshot.mcp</info>

                    Scan multiple specific directories:
                      <info>php %command.full_name% src/ config/ docs/</info>

                    Include everything (ignore gitignore and vendors):
                      <info>php %command.full_name% --no-gitignore --include-vendors</info>

                    Only process PHP and JavaScript files:
                      <info>php %command.full_name% --extensions=php,javascript</info>

                    Ignore specific patterns:
                      <info>php %command.full_name% --ignore="*.log" --ignore="temp/" --ignore="cache"</info>

                    Advanced filtering with limits:
                      <info>php %command.full_name% --max-files=1000 --max-size=2048 --max-lines=2000</info>

                    <info>Output format:</info>
                      The command generates MCP (Markdown Context Pack) format that includes:
                      • Project metadata in JSON format
                      • Directory structure tree visualization
                      • File contents grouped by programming language
                      • Comprehensive statistics and breakdown
                      • Syntax highlighting for code blocks

                    <info>Default behavior:</info>
                      • Excludes vendor/, node_modules/, .git/, cache, logs, binaries
                      • Includes source code, config files, documentation
                      • Respects .gitignore patterns (can be overridden with --no-gitignore)
                      • Limits file size to 1MB and file count to 500 (configurable)
                      • Automatically detects file types and programming languages
                    EOF
            );
    }

    /**
     * Build configuration array for the snapshot service.
     *
     * Processes all command options and converts them into the configuration
     * format expected by the SnapshotService.
     *
     * @param array<string> $paths Array of valid paths to process
     */
    private function buildConfig(
        InputInterface $input,
        array $paths,
    ): array {
        $config = [
            'paths' => $paths,
            'output_file' => $input->getOption('output') ?? 'snapshots/snapshot.mcp',
            'no_gitignore' => $input->getOption('no-gitignore'),
            'include_vendors' => $input->getOption('include-vendors'),
            'include_hidden' => $input->getOption('include-hidden'),
        ];

        // Convert numeric options with proper validation
        $maxFiles = $this->parseNumericOption($input->getOption('max-files'));
        $maxSize = $this->parseNumericOption($input->getOption('max-size'));
        $maxLines = $this->parseNumericOption($input->getOption('max-lines'));

        $config['max_files'] = max($maxFiles, 0);
        $config['max_file_size'] = max($maxSize, 0);
        $config['max_lines'] = max($maxLines, 0);

        // Process extensions option
        $extensions = $input->getOption('extensions');

        if (!empty($extensions)) {
            $config['allowed_extensions'] = $this->parseExtensions($extensions);
        }

        // Process ignore patterns
        $ignorePatterns = $input->getOption('ignore');

        if (!empty($ignorePatterns)) {
            $config['ignore_patterns'] = $this->parseIgnorePatterns($ignorePatterns);
        }

        return $config;
    }

    /**
     * Check if a path is absolute.
     */
    private function isAbsolutePath(
        string $path,
    ): bool {
        return str_starts_with($path, '/') || (PHP_OS === 'WINNT' && str_contains($path, ':'));
    }

    /**
     * Parse comma-separated extensions into an array.
     */
    private function parseExtensions(
        string $extensions,
    ): array {
        return array_map('trim', explode(',', $extensions));
    }

    /**
     * Parse ignore patterns into structured array.
     */
    private function parseIgnorePatterns(
        array $patterns,
    ): array {
        $structured = [
            'files' => [],
            'dirs' => [],
            'extensions' => [],
            'paths' => [],
        ];

        foreach ($patterns as $pattern) {
            if (empty($pattern)) {
                continue;
            }

            $pattern = trim($pattern);

            // Directory pattern (ends with /)
            if (str_ends_with($pattern, '/')) {
                $structured['dirs'][] = $pattern;
            }
            // Extension pattern (starts with *. or contains .)
            elseif (str_starts_with($pattern, '*.') && !str_contains($pattern, '/')) {
                $structured['extensions'][] = substr($pattern, 2);
            }
            // Path pattern (contains /)
            elseif (str_contains($pattern, '/')) {
                $structured['paths'][] = $pattern;
            }
            // File pattern (simple filename)
            else {
                $structured['files'][] = $pattern;
            }
        }

        return $structured;
    }

    /**
     * Parse numeric option with validation.
     */
    private function parseNumericOption(
        $value,
    ): int {
        if (is_array($value)) {
            $value = $value[0] ?? 0;
        }

        return (int) $value;
    }

    /**
     * Prepare and validate paths from command input.
     *
     * Processes the paths argument, validates that they exist and are directories,
     * and returns an array of valid absolute paths.
     *
     * @return array<string> Array of valid absolute paths
     */
    private function preparePaths(
        InputInterface $input,
        $io,
    ): array {
        $paths = $input->getArgument('paths');
        $validPaths = [];

        foreach ($paths as $path) {
            // Convert to absolute path
            if (!$this->isAbsolutePath($path)) {
                $path = getcwd() . '/' . $path;
            }

            // Validate path exists and is a directory
            if (!is_dir($path)) {
                $io->warning("Path does not exist or is not a directory: $path");

                continue;
            }

            // Convert to real path for consistency
            $realPath = realpath($path);

            if (false !== $realPath) {
                $validPaths[] = $realPath;
            }
        }

        if (empty($validPaths)) {
            $io->error('No valid paths found to process.');
        }

        return $validPaths;
    }
}

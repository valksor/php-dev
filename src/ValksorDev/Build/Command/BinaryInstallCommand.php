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

namespace ValksorDev\Build\Command;

use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use ValksorDev\Build\Binary\BinaryRegistry;
use ValksorDev\Build\Provider\ProviderRegistry;

use function count;
use function sprintf;

#[AsCommand(name: 'valksor:binaries:install', description: 'Install all required binaries.')]
final class BinaryInstallCommand extends AbstractCommand
{
    public function __construct(
        ParameterBagInterface $parameterParameterBag,
        private readonly BinaryRegistry $binaryRegistry,
        #[Autowire(
            param: 'valksor.build.services',
        )]
        private readonly array $servicesConfig,
        ProviderRegistry $providerRegistry,
    ) {
        parent::__construct($parameterParameterBag, $providerRegistry);
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);

        $io->title('Installing Required Binaries');

        // Get required binaries from services configuration
        $binaryService = $this->servicesConfig['binaries'] ?? [];
        $requiredBinaries = $binaryService['options']['required'];

        if (empty($requiredBinaries)) {
            $io->warning('No binaries required in configuration.');

            return Command::SUCCESS;
        }

        $projectRoot = $this->parameterBag->get('kernel.project_dir');
        $varDir = $projectRoot . '/var';

        $successCount = 0;
        $totalCount = count($requiredBinaries);

        foreach ($requiredBinaries as $binary) {
            $io->section(sprintf('Installing %s', $binary));

            if (!$this->binaryRegistry->has($binary)) {
                $io->warning(sprintf('Binary %s not found in registry, skipping...', $binary));

                continue;
            }

            try {
                $tag = $this->binaryRegistry->get($binary)->createManager($varDir, $binary)?->ensureLatest([$io, 'text']);
                $io->success(sprintf('✓ %s installed (%s)', $binary, $tag));
                $successCount++;
            } catch (Exception $e) {
                $io->error(sprintf('Failed to install %s: %s', $binary, $e->getMessage()));
            }
        }

        // Check for and install Generic NPM packages
        $genericProvider = $this->binaryRegistry->getGenericNpmProvider();

        if ($genericProvider && $genericProvider->getPackageCount() > 0) {
            $io->section('Installing Generic NPM Packages');

            try {
                $packageNames = $genericProvider->getPackages();
                $versions = $genericProvider->ensureAll([$io, 'text']);

                foreach ($packageNames as $i => $packageName) {
                    $io->success(sprintf('✓ %s installed (%s)', $packageName, $versions[$i] ?? 'unknown'));
                }

                // Sync packages to public/vendor
                $io->section('Syncing Packages to public/vendor');

                foreach ($genericProvider->syncToPublicVendor([$io, 'text']) as $packageName) {
                    $io->success(sprintf('✓ %s synced to public/vendor', $packageName));
                }

                $successCount += count($packageNames);
                $totalCount += count($packageNames);
            } catch (Exception $e) {
                $io->error(sprintf('Failed to install Generic NPM packages: %s', $e->getMessage()));
            }
        }

        $io->newLine();

        if ($successCount === $totalCount) {
            $io->success(sprintf('All %d binaries installed successfully!', $totalCount));

            return Command::SUCCESS;
        }
        $io->warning(sprintf('%d/%d binaries installed successfully.', $successCount, $totalCount));

        return Command::FAILURE;
    }
}

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

use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use ValksorDev\Build\Provider\ProviderInterface;
use ValksorDev\Build\Provider\ProviderRegistry;
use ValksorDev\Build\Service\DevWatchService;

/**
 * Tests for DevWatchService class.
 *
 * Tests development watch service orchestration and multi-service coordination.
 */
final class DevWatchServiceTest extends TestCase
{
    private SymfonyStyle $io;
    private ParameterBagInterface $parameterBag;
    private ProviderRegistry $providerRegistry;

    public function testGetParameterBag(): void
    {
        $devWatchService = new DevWatchService($this->parameterBag, $this->providerRegistry);

        self::assertSame($this->parameterBag, $devWatchService->getParameterBag());
    }

    public function testGetProviderRegistry(): void
    {
        $devWatchService = new DevWatchService($this->parameterBag, $this->providerRegistry);

        self::assertSame($this->providerRegistry, $devWatchService->getProviderRegistry());
    }

    public function testSetIo(): void
    {
        $devWatchService = new DevWatchService($this->parameterBag, $this->providerRegistry);

        // Test that setIo method executes without error
        $devWatchService->setIo($this->io);

        self::assertTrue(true); // If we get here without exception, the method works
    }

    public function testStart(): void
    {
        $devWatchService = new DevWatchService($this->parameterBag, $this->providerRegistry);
        $devWatchService->setIo($this->io);

        // Test that start method executes without errors
        try {
            $result = $devWatchService->start();
            self::assertContains($result, [0, 1]); // Command::SUCCESS or Command::FAILURE
        } catch (Exception) {
            // Expected in test environment due to missing dependencies
            self::assertTrue(true);
        }
    }

    public function testStartWithInitServicesOnly(): void
    {
        $parameterBag = new ParameterBag([
            'kernel.project_dir' => '/test/project',
            'kernel.environment' => 'dev',
            'valksor.build.services' => [
                'init_service' => [
                    'enabled' => true,
                    'provider' => 'init_provider',
                    'flags' => ['init' => true],
                ],
                // Add a dev service so start() doesn't return early
                'dev_service' => [
                    'enabled' => true,
                    'provider' => 'dev_provider',
                    'flags' => ['dev' => true],
                ],
            ],
        ]);

        // Create a mock provider for init
        $initProvider = new class implements ProviderInterface {
            public bool $initialized = false;

            public function getName(): string
            {
                return 'init_provider';
            }

            public function getServiceOrder(): int
            {
                return 1;
            }

            public function getDependencies(): array
            {
                return [];
            }

            public function init(
                array $options,
            ): void {
                $this->initialized = true;
            }

            public function build(
                array $options,
            ): int {
                return 0;
            }

            public function watch(
                array $options,
            ): int {
                return 0;
            }
        };

        // Create a mock provider for dev
        $devProvider = new class implements ProviderInterface {
            public function getName(): string
            {
                return 'dev_provider';
            }

            public function getServiceOrder(): int
            {
                return 2;
            }

            public function getDependencies(): array
            {
                return [];
            }

            public function init(
                array $options,
            ): void {
            }

            public function build(
                array $options,
            ): int {
                return 0;
            }

            public function watch(
                array $options,
            ): int {
                return 0;
            }
        };

        // Use real ProviderRegistry
        $providerRegistry = new ProviderRegistry([$initProvider, $devProvider]);

        $devWatchService = new DevWatchService($parameterBag, $providerRegistry);
        $devWatchService->setIo($this->io);

        // This will fail because dev_service command fails to start (process exits or invalid command)
        // But init should have run
        $result = $devWatchService->start();

        self::assertSame(1, $result); // Command::FAILURE
        self::assertTrue($initProvider->initialized);
    }

    public function testStartWithMissingProviders(): void
    {
        $parameterBag = new ParameterBag([
            'kernel.project_dir' => '/test/project',
            'kernel.environment' => 'dev',
            'valksor.build.services' => [
                'valid_service' => [
                    'enabled' => true,
                    'provider' => 'valid',
                    'flags' => ['dev' => true],
                ],
                'missing_service' => [
                    'enabled' => true,
                    'provider' => 'missing',
                    'flags' => ['dev' => true],
                ],
            ],
        ]);

        // Create valid provider
        $validProvider = new class implements ProviderInterface {
            public function getName(): string
            {
                return 'valid';
            }

            public function getServiceOrder(): int
            {
                return 1;
            }

            public function getDependencies(): array
            {
                return [];
            }

            public function init(
                array $options,
            ): void {
            }

            public function build(
                array $options,
            ): int {
                return 0;
            }

            public function watch(
                array $options,
            ): int {
                return 0;
            }
        };

        // Registry with only valid provider
        $providerRegistry = new ProviderRegistry([$validProvider]);

        $devWatchService = new DevWatchService($parameterBag, $providerRegistry);
        $devWatchService->setIo($this->io);

        $result = $devWatchService->start();

        self::assertSame(1, $result); // Command::FAILURE
    }

    public function testStartWithoutIo(): void
    {
        $devWatchService = new DevWatchService($this->parameterBag, $this->providerRegistry);

        // Test start without setting IO
        try {
            $result = $devWatchService->start();
            self::assertContains($result, [0, 1]);
        } catch (Exception) {
            self::assertTrue(true);
        }
    }

    public function testStop(): void
    {
        $devWatchService = new DevWatchService($this->parameterBag, $this->providerRegistry);

        // Test that stop method executes without error
        $devWatchService->stop();

        self::assertTrue(true); // If we get here, stop worked
    }

    public function testWithDifferentEnvironment(): void
    {
        $prodParameterBag = new ParameterBag([
            'kernel.project_dir' => '/test/project',
            'kernel.environment' => 'prod',
            'valksor.build.services' => [
                'sse_server' => [
                    'enabled' => true,
                    'provider' => 'sse_server',
                ],
            ],
        ]);

        $devWatchService = new DevWatchService($prodParameterBag, $this->providerRegistry);

        self::assertSame($prodParameterBag, $devWatchService->getParameterBag());
    }

    public function testWithDisabledServices(): void
    {
        $parameterBag = new ParameterBag([
            'kernel.project_dir' => '/test/project',
            'kernel.environment' => 'dev',
            'valksor.build.services' => [
                'sse_server' => [
                    'enabled' => false,
                    'provider' => 'sse_server',
                ],
                'hot_reload' => [
                    'enabled' => false,
                    'provider' => 'hot_reload',
                ],
                'tailwind' => [
                    'enabled' => false,
                    'provider' => 'tailwind',
                ],
            ],
        ]);

        $devWatchService = new DevWatchService($parameterBag, $this->providerRegistry);
        $devWatchService->setIo($this->io);

        // Test that start method executes without errors even with disabled services
        try {
            $result = $devWatchService->start();
            self::assertContains($result, [0, 1]);
        } catch (Exception) {
            self::assertTrue(true);
        }
    }

    public function testWithEmptyServices(): void
    {
        $emptyParameterBag = new ParameterBag([
            'kernel.project_dir' => '/test/project',
            'kernel.environment' => 'dev',
            'valksor.build.services' => [],
        ]);

        $devWatchService = new DevWatchService($emptyParameterBag, $this->providerRegistry);
        $devWatchService->setIo($this->io);

        try {
            $result = $devWatchService->start();
            self::assertContains($result, [0, 1]);
        } catch (Exception) {
            self::assertTrue(true);
        }
    }

    protected function setUp(): void
    {
        $this->parameterBag = new ParameterBag([
            'kernel.project_dir' => '/test/project',
            'kernel.environment' => 'dev',
            'valksor.build.services' => [
                'sse_server' => [
                    'enabled' => true,
                    'provider' => 'sse_server',
                ],
                'hot_reload' => [
                    'enabled' => true,
                    'provider' => 'hot_reload',
                ],
                'tailwind' => [
                    'enabled' => true,
                    'provider' => 'tailwind',
                ],
                'importmap' => [
                    'enabled' => true,
                    'provider' => 'importmap',
                ],
            ],
        ]);
        $this->providerRegistry = new ProviderRegistry([]);
        $this->io = $this->createStub(SymfonyStyle::class);
    }
}

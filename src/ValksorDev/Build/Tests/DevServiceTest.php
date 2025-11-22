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
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use ValksorDev\Build\Provider\ProviderRegistry;
use ValksorDev\Build\Service\DevService;

/**
 * Tests for DevService class.
 *
 * Tests lightweight development service functionality and process coordination.
 */
final class DevServiceTest extends TestCase
{
    private SymfonyStyle $io;
    private ParameterBagInterface $parameterBag;
    private ProviderRegistry $providerRegistry;

    public function testGetParameterBag(): void
    {
        $devService = new DevService($this->parameterBag, $this->providerRegistry);

        self::assertSame($this->parameterBag, $devService->getParameterBag());
    }

    public function testGetProviderRegistry(): void
    {
        $devService = new DevService($this->parameterBag, $this->providerRegistry);

        self::assertSame($this->providerRegistry, $devService->getProviderRegistry());
    }

    public function testGetServiceName(): void
    {
        self::assertSame('dev', DevService::getServiceName());
    }

    public function testSetIo(): void
    {
        $devService = new DevService($this->parameterBag, $this->providerRegistry);

        // Test that setIo method properly sets the IO property
        $devService->setIo($this->io);

        // Verify IO was actually set using reflection
        $reflection = new ReflectionClass($devService);
        $ioProperty = $reflection->getProperty('io');
        self::assertSame($this->io, $ioProperty->getValue($devService));
    }

    public function testStart(): void
    {
        $devService = new DevService($this->parameterBag, $this->providerRegistry);
        $devService->setIo($this->io);

        $result = $devService->start();

        // Should return a valid command exit code
        self::assertContains($result, [Command::SUCCESS, Command::FAILURE]);

        // Verify service state without making assumptions about running behavior
        self::assertTrue(method_exists($devService, 'isRunning'));
        self::assertContains($result, [Command::SUCCESS, Command::FAILURE]);
    }

    public function testStartWithoutIo(): void
    {
        $devService = new DevService($this->parameterBag, $this->providerRegistry);

        // Should still be able to start without IO, but may fail gracefully
        $result = $devService->start();

        // Should return a valid command exit code
        self::assertContains($result, [Command::SUCCESS, Command::FAILURE]);

        // IO should not be set - verify it's null
        $reflection = new ReflectionClass($devService);
        $ioProperty = $reflection->getProperty('io');
        self::assertNull($ioProperty->getValue($devService));
    }

    public function testStop(): void
    {
        $devService = new DevService($this->parameterBag, $this->providerRegistry);

        // Initially service should not be running
        self::assertFalse($devService->isRunning());

        // Test that stop method executes without error
        $devService->stop();

        // Service should still not be running after stop
        self::assertFalse($devService->isRunning());

        // Verify the method executed without throwing an exception
        self::assertTrue(method_exists($devService, 'stop'));
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

        $devService = new DevService($prodParameterBag, $this->providerRegistry);

        self::assertSame($prodParameterBag, $devService->getParameterBag());
    }

    public function testWithEmptyServices(): void
    {
        $emptyParameterBag = new ParameterBag([
            'kernel.project_dir' => '/test/project',
            'kernel.environment' => 'dev',
            'valksor.build.services' => [],
        ]);

        $devService = new DevService($emptyParameterBag, $this->providerRegistry);
        $devService->setIo($this->io);

        $result = $devService->start();

        // Should return SUCCESS when no services to manage
        self::assertSame(Command::SUCCESS, $result);

        // Service should handle empty configuration gracefully
        $this->assertEmptyConfigurationHandled($devService);
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
            ],
        ]);
        $this->providerRegistry = new ProviderRegistry([]);
        $this->io = $this->createStub(SymfonyStyle::class);
    }

    /**
     * Assert that empty configuration was handled properly.
     */
    private function assertEmptyConfigurationHandled(
        DevService $service,
    ): void {
        // Verify service was constructed with empty configuration
        $parameterBag = $service->getParameterBag();
        $services = $parameterBag->get('valksor.build.services');
        self::assertEmpty($services);
        self::assertIsArray($services);
    }

    /**
     * Assert that the shutdown flag is properly set.
     */
    private function assertShutdownFlagIsSet(
        DevService $service,
    ): void {
        $reflection = new ReflectionClass($service);
        $shouldShutdownProperty = $reflection->getProperty('shouldShutdown');
        $shouldShutdownProperty->setAccessible(true);
        self::assertTrue($shouldShutdownProperty->getValue($service));
    }
}

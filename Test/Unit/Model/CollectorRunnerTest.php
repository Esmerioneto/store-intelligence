<?php
/**
 * Esmerio Neto
 *
 * @category Egsn
 * @package Egsn_StoreIntelligence
 *
 * @copyright Copyright (c) 2026 Esmerio Neto.
 *
 * @author Esmerio Neto <esmerioneto@gmail.com>
 */
declare(strict_types=1);

namespace Egsn\StoreIntelligence\Test\Unit\Model;

use Egsn\StoreIntelligence\Collector\CollectionResult;
use Egsn\StoreIntelligence\Collector\CollectorInterface;
use Egsn\StoreIntelligence\Model\CollectorRunner;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CollectorRunnerTest extends TestCase
{
    /**
     * @var CollectorRunner
     */
    private CollectorRunner $runner;
    /**
     * @var MockObject
     */
    private MockObject $scopeConfig;
    /**
     * @var MockObject
     */
    private MockObject $logger;

    /**
     * Set up.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->logger      = $this->createMock(LoggerInterface::class);
        $this->runner      = new CollectorRunner($this->scopeConfig, $this->logger);
    }

    /**
     * Test runs collector and returns result.
     *
     * @return void
     */
    public function testRunsCollectorAndReturnsResult(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(30);

        $result    = new CollectionResult('test', 'performance', 'ok', ['count' => 0]);
        $collector = $this->createMock(CollectorInterface::class);
        $collector->method('collect')->willReturn($result);
        $collector->method('getCode')->willReturn('test');
        $collector->method('getCategory')->willReturn('performance');

        $actual = $this->runner->run($collector);

        $this->assertSame($result, $actual);
    }

    /**
     * Test returns null and logs when collector throws.
     *
     * @return void
     */
    public function testReturnsNullAndLogsWhenCollectorThrows(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(30);

        $collector = $this->createMock(CollectorInterface::class);
        $collector->method('collect')->willThrowException(new \RuntimeException('DB error'));
        $collector->method('getCode')->willReturn('slow_queries');
        $collector->method('getCategory')->willReturn('performance');

        $this->logger->expects($this->once())->method('error');

        $result = $this->runner->run($collector);

        $this->assertNull($result);
    }

    /**
     * Test returns null and logs on timeout.
     *
     * @return void
     */
    public function testReturnsNullAndLogsOnTimeout(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(0.000001);

        $collector = $this->createMock(CollectorInterface::class);
        $collector->method('collect')->willReturnCallback(function () {
            usleep(10000); // 10ms > 0.000001s
            return new CollectionResult('test', 'performance', 'ok', []);
        });
        $collector->method('getCode')->willReturn('test');
        $collector->method('getCategory')->willReturn('performance');

        $this->logger->expects($this->once())->method('warning');

        $result = $this->runner->run($collector);

        $this->assertNull($result);
    }
}

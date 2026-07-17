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

namespace Egsn\StoreIntelligence\Test\Unit\Collector\Errors;

use Egsn\StoreIntelligence\Collector\Errors\FailedPaymentsCollector;
use Egsn\StoreIntelligence\Model\AnalysisScope;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

class FailedPaymentsCollectorTest extends TestCase
{
    /**
     * Make collector.
     *
     * @param array $rows
     * @return FailedPaymentsCollector
     */
    private function makeCollector(array $rows): FailedPaymentsCollector
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('fetchAll')->willReturn($rows);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        return new FailedPaymentsCollector($resource, $this->createMock(AnalysisScope::class));
    }

    /**
     * Test collect returns ok with no stuck orders.
     *
     * @return void
     */
    public function testCollectReturnsOkWithNoStuckOrders(): void
    {
        $result = $this->makeCollector([])->collect();

        $this->assertSame('failed_payments', $result->getCollectorCode());
        $this->assertSame('errors', $result->getCategory());
        $this->assertSame('ok', $result->getStatus());
        $this->assertSame(0, $result->getSummary()['count']);
        $this->assertSame(100, $result->getScore());
    }

    /**
     * Test collect flags stuck orders and computes score.
     *
     * @return void
     */
    public function testCollectFlagsStuckOrdersAndComputesScore(): void
    {
        $rows = [
            ['increment_id' => '100000001', 'status' => 'pending_payment',
             'grand_total' => 150.00, 'created_at' => '2026-01-01', 'payment_method' => 'checkmo'],
            ['increment_id' => '100000002', 'status' => 'payment_review',
             'grand_total' => 50.00, 'created_at' => '2026-01-02', 'payment_method' => null],
        ];

        $result = $this->makeCollector($rows)->collect();

        $this->assertSame('warning', $result->getStatus());
        $this->assertSame(2, $result->getSummary()['count']);
        $this->assertSame(200.0, $result->getSummary()['total_value']);
        $this->assertSame(90, $result->getScore());
        $this->assertSame('—', $result->getItems()[1]['payment_method']);
    }
}

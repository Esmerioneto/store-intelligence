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

namespace Egsn\StoreIntelligence\Test\Unit\Collector\Sales;

use Egsn\StoreIntelligence\Collector\Sales\AbandonedCartsCollector;
use Egsn\StoreIntelligence\Model\AnalysisScope;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

class AbandonedCartsCollectorTest extends TestCase
{
    /**
     * Make collector.
     *
     * @param array $rows
     * @return AbandonedCartsCollector
     */
    private function makeCollector(array $rows): AbandonedCartsCollector
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('fetchAll')->willReturn($rows);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn(2);

        return new AbandonedCartsCollector($resource, $config, $this->createMock(AnalysisScope::class));
    }

    /**
     * Test collect returns ok with no abandoned carts.
     *
     * @return void
     */
    public function testCollectReturnsOkWithNoAbandonedCarts(): void
    {
        $result = $this->makeCollector([])->collect();

        $this->assertSame('abandoned_carts', $result->getCollectorCode());
        $this->assertSame('ok', $result->getStatus());
        $this->assertSame(0, $result->getSummary()['count']);
    }

    /**
     * Test collect sums abandoned value.
     *
     * @return void
     */
    public function testCollectSumsAbandonedValue(): void
    {
        $rows = [
            ['quote_id' => 1, 'items_count' => 2, 'grand_total' => 100.50, 'updated_at' => '2026-01-01'],
            ['quote_id' => 2, 'items_count' => 1, 'grand_total' => 49.50, 'updated_at' => '2026-01-02'],
        ];

        $result = $this->makeCollector($rows)->collect();

        $this->assertSame('warning', $result->getStatus());
        $this->assertSame(2, $result->getSummary()['count']);
        $this->assertSame(150.0, $result->getSummary()['total_value']);
        $this->assertSame(2, $result->getSummary()['threshold_hours']);
    }
}

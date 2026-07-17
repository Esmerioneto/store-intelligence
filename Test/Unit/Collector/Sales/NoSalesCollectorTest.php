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

use Egsn\StoreIntelligence\Collector\Sales\NoSalesCollector;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

class NoSalesCollectorTest extends TestCase
{
    /**
     * Test collect returns correct structure.
     *
     * @return void
     */
    public function testCollectReturnsCorrectStructure(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('fetchAll')->willReturn([
            ['product_id' => 1, 'sku' => 'TEST-001', 'name' => 'Produto Teste',
             'price' => 99.90, 'cost' => 50.00, 'stock_qty' => 10,
             'last_sale_date' => null, 'days_without_sale' => 999,
             'total_sold_ever' => 0, 'category' => 'Teste'],
        ]);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);

        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn(90);

        $collector = new NoSalesCollector(
            $resource,
            $config,
            $this->createMock(\Egsn\StoreIntelligence\Model\AnalysisScope::class),
            $this->makeMetadataPool()
        );
        $result    = $collector->collect();

        $this->assertSame('no_sales', $result->getCollectorCode());
        $this->assertSame('sales', $result->getCategory());
        $this->assertSame(1, $result->getSummary()['count']);
        $this->assertCount(1, $result->getItems());
        $this->assertSame('TEST-001', $result->getItems()[0]['sku']);
    }

    /**
     * Test collect returns ok when no products.
     *
     * @return void
     */
    public function testCollectReturnsOkWhenNoProducts(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('fetchAll')->willReturn([]);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);

        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn(90);

        $collector = new NoSalesCollector(
            $resource,
            $config,
            $this->createMock(\Egsn\StoreIntelligence\Model\AnalysisScope::class),
            $this->makeMetadataPool()
        );
        $result    = $collector->collect();

        $this->assertSame('ok', $result->getStatus());
        $this->assertSame(0, $result->getSummary()['count']);
    }

    /**
     * Make metadata pool.
     *
     * @return \Magento\Framework\EntityManager\MetadataPool
     */
    private function makeMetadataPool(): \Magento\Framework\EntityManager\MetadataPool
    {
        $metadata = $this->createMock(\Magento\Framework\EntityManager\EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn('entity_id');
        $pool = $this->createMock(\Magento\Framework\EntityManager\MetadataPool::class);
        $pool->method('getMetadata')->willReturn($metadata);
        return $pool;
    }
}

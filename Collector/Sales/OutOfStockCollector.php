<?php
/**
 * Esmerio Neto
 *
 * NOTICE OF LICENSE
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future.
 *
 * @category Egsn
 * @package Egsn_StoreIntelligence
 *
 * @copyright Copyright (c) 2026 Esmerio Neto.
 *
 * @author Esmerio Neto <esmerioneto@gmail.com>
 */
declare(strict_types=1);

// phpcs:disable Magento2.SQL.RawQuery.FoundRawSql -- SQL raw deliberado: só inteiros e nomes de tabela interpolados

namespace Egsn\StoreIntelligence\Collector\Sales;

use Egsn\StoreIntelligence\Collector\CollectionResult;
use Egsn\StoreIntelligence\Collector\CollectorInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\App\ResourceConnection;

class OutOfStockCollector implements CollectorInterface
{
    /**
     * Constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly MetadataPool $metadataPool
    ) {
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return 'out_of_stock';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Produtos Sem Estoque';
    }

    /**
     * Get category.
     *
     * @return string
     */
    public function getCategory(): string
    {
        return 'sales';
    }

    /**
     * Collect.
     *
     * @return CollectionResult
     */
    public function collect(): CollectionResult
    {
        try {
            $connection   = $this->resourceConnection->getConnection();
            $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
            $productTable = $this->resourceConnection->getTableName('catalog_product_entity');
            $stockTable   = $this->resourceConnection->getTableName('cataloginventory_stock_item');
            $varcharTable = $this->resourceConnection->getTableName('catalog_product_entity_varchar');
            $eavTable     = $this->resourceConnection->getTableName('eav_attribute');

            $sql = "SELECT p.entity_id AS product_id, p.sku, pname.value AS name,
                           si.qty, si.is_in_stock
                    FROM {$productTable} p
                    JOIN {$stockTable} si ON si.product_id = p.entity_id
                    LEFT JOIN {$varcharTable} pname
                        ON pname.{$linkField} = p.{$linkField}
                        AND pname.attribute_id = (
                            SELECT attribute_id FROM {$eavTable}
                            WHERE attribute_code='name' AND entity_type_id=4
                        )
                        AND pname.store_id = 0
                    WHERE si.qty <= 0 AND si.is_in_stock = 0
                    LIMIT 200";

            $rows = $connection->fetchAll($sql);
        } catch (\Throwable $e) {
            return new CollectionResult(
                $this->getCode(),
                $this->getCategory(),
                'ok',
                ['count' => 0, 'note' => 'inventory tables not accessible: ' . $e->getMessage()]
            );
        }

        $count = count($rows);
        $items = [];
        foreach ($rows as $row) {
            $productId = (int) $row['product_id'];
            $items[]   = [
                'product_id'    => $productId,
                'sku'           => $row['sku'],
                'name'          => $row['name'],
                'qty'           => (float) $row['qty'],
                'is_in_stock'   => (int) $row['is_in_stock'],
            ];
        }

        $status = $count === 0 ? 'ok' : ($count < 20 ? 'warning' : 'critical');

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: ['count' => $count],
            items: $items
        );
    }
}

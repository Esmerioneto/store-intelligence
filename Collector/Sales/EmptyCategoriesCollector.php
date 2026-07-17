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
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\App\ResourceConnection;

class EmptyCategoriesCollector implements CollectorInterface
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
        return 'empty_categories';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Categorias Vazias';
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
            $connection    = $this->resourceConnection->getConnection();
            $categoryLinkField = $this->metadataPool->getMetadata(CategoryInterface::class)->getLinkField();
            $categoryTable = $this->resourceConnection->getTableName('catalog_category_entity');
            $varcharTable  = $this->resourceConnection->getTableName('catalog_category_entity_varchar');
            $cpTable       = $this->resourceConnection->getTableName('catalog_category_product');
            $eavTable      = $this->resourceConnection->getTableName('eav_attribute');

            $sql = "SELECT c.entity_id AS category_id, cname.value AS name,
                           COUNT(cp.product_id) AS product_count
                    FROM {$categoryTable} c
                    LEFT JOIN {$varcharTable} cname
                        ON cname.{$categoryLinkField} = c.{$categoryLinkField}
                        AND cname.attribute_id = (
                            SELECT attribute_id FROM {$eavTable}
                            WHERE attribute_code='name' AND entity_type_id=3
                        )
                        AND cname.store_id = 0
                    LEFT JOIN {$cpTable} cp ON cp.category_id = c.entity_id
                    WHERE c.level > 1
                    GROUP BY c.entity_id
                    HAVING product_count = 0
                    LIMIT 50";

            $rows = $connection->fetchAll($sql);
        } catch (\Throwable $e) {
            return new CollectionResult(
                $this->getCode(),
                $this->getCategory(),
                'ok',
                ['count' => 0, 'note' => 'catalog_category tables not accessible: ' . $e->getMessage()]
            );
        }

        $count = count($rows);
        $items = [];
        foreach ($rows as $row) {
            $categoryId = (int) $row['category_id'];
            $items[]    = [
                'category_id'    => $categoryId,
                'name'           => $row['name'],
                'product_count'  => (int) $row['product_count'],
            ];
        }

        $status = $count === 0 ? 'ok' : ($count < 5 ? 'warning' : 'critical');

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: ['count' => $count],
            items: $items
        );
    }
}

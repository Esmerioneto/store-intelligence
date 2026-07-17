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
use Egsn\StoreIntelligence\Model\AnalysisScope;
use Magento\Framework\App\ResourceConnection;

class CrossSellOpportunitiesCollector implements CollectorInterface
{
    /**
     * Constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param AnalysisScope $analysisScope
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly AnalysisScope $analysisScope,
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
        return 'cross_sell_opportunities';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Oportunidades de Cross-Sell';
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
            $orderItem    = $this->resourceConnection->getTableName('sales_order_item');
            $storeFilter  = $this->analysisScope->storeFilterSql('a.store_id');
            $varcharTable = $this->resourceConnection->getTableName('catalog_product_entity_varchar');
            $productTable = $this->resourceConnection->getTableName('catalog_product_entity');
            $eavTable     = $this->resourceConnection->getTableName('eav_attribute');

            $sql = "SELECT a.product_id AS product_a_id, b.product_id AS product_b_id,
                           COUNT(*) AS times_bought_together,
                           pa_name.value AS product_a_name, pb_name.value AS product_b_name
                    FROM {$orderItem} a
                    JOIN {$orderItem} b ON b.order_id = a.order_id AND b.product_id > a.product_id
                    LEFT JOIN {$productTable} pae ON pae.entity_id = a.product_id
                    LEFT JOIN {$varcharTable} pa_name
                        ON pa_name.{$linkField} = pae.{$linkField}
                        AND pa_name.attribute_id = (
                            SELECT attribute_id FROM {$eavTable}
                            WHERE attribute_code='name' AND entity_type_id=4
                        )
                        AND pa_name.store_id = 0
                    LEFT JOIN {$productTable} pbe ON pbe.entity_id = b.product_id
                    LEFT JOIN {$varcharTable} pb_name
                        ON pb_name.{$linkField} = pbe.{$linkField}
                        AND pb_name.attribute_id = (
                            SELECT attribute_id FROM {$eavTable}
                            WHERE attribute_code='name' AND entity_type_id=4
                        )
                        AND pb_name.store_id = 0
                    WHERE a.created_at > DATE_SUB(NOW(), INTERVAL 90 DAY){$storeFilter}
                    GROUP BY a.product_id, b.product_id
                    HAVING times_bought_together >= 2
                    ORDER BY times_bought_together DESC
                    LIMIT 20";

            $rows = $connection->fetchAll($sql);
        } catch (\Throwable $e) {
            return new CollectionResult(
                $this->getCode(),
                $this->getCategory(),
                'ok',
                ['pairs_found' => 0, 'note' => 'sales_order_item table not accessible: ' . $e->getMessage()]
            );
        }

        $pairsFound = count($rows);
        $items      = [];
        foreach ($rows as $row) {
            $items[] = [
                'product_a_id'         => (int) $row['product_a_id'],
                'product_b_id'         => (int) $row['product_b_id'],
                'product_a_name'       => $row['product_a_name'],
                'product_b_name'       => $row['product_b_name'],
                'times_bought_together' => (int) $row['times_bought_together'],
            ];
        }

        $status = 'ok';

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: ['pairs_found' => $pairsFound],
            items: $items
        );
    }
}

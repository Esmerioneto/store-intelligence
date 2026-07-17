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

class LeastViewedProductsCollector implements CollectorInterface
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
        return 'least_viewed';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Produtos Menos Visualizados';
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
            $varcharTable = $this->resourceConnection->getTableName('catalog_product_entity_varchar');
            $viewsTable   = $this->resourceConnection->getTableName('report_viewed_product_aggregated_daily');
            $storeFilter  = $this->analysisScope->storeFilterSql('store_id');
            $eavTable     = $this->resourceConnection->getTableName('eav_attribute');

            $sql = "SELECT p.entity_id AS product_id, p.sku, pname.value AS name,
                           COALESCE(views.views_count, 0) AS views
                    FROM {$productTable} p
                    LEFT JOIN {$varcharTable} pname
                        ON pname.{$linkField} = p.{$linkField}
                        AND pname.attribute_id = (
                            SELECT attribute_id FROM {$eavTable}
                            WHERE attribute_code='name' AND entity_type_id=4
                        )
                        AND pname.store_id = 0
                    LEFT JOIN (
                        SELECT product_id, SUM(views_num) AS views_count
                        FROM {$viewsTable}
                        WHERE period > DATE_SUB(NOW(), INTERVAL 30 DAY){$storeFilter}
                        GROUP BY product_id
                    ) views ON views.product_id = p.entity_id
                    WHERE p.type_id = 'simple'{$this->analysisScope->productWebsiteFilterSql('p.entity_id')}
                    ORDER BY views ASC
                    LIMIT 50";

            $rows = $connection->fetchAll($sql);
        } catch (\Throwable $e) {
            return new CollectionResult(
                $this->getCode(),
                $this->getCategory(),
                'ok',
                ['count' => 0, 'period_days' => 30, 'note' => 'report tables not accessible: ' . $e->getMessage()]
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
                'views'         => (int) $row['views'],
            ];
        }

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: 'ok',
            summary: ['count' => $count, 'period_days' => 30],
            items: $items
        );
    }
}

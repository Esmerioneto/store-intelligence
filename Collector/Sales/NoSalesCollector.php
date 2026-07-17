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

namespace Egsn\StoreIntelligence\Collector\Sales;

use Egsn\StoreIntelligence\Collector\CollectionResult;
use Egsn\StoreIntelligence\Collector\CollectorInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Egsn\StoreIntelligence\Model\AnalysisScope;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;

class NoSalesCollector implements CollectorInterface
{
    private const CONFIG_THRESHOLD = 'egsn_si/thresholds/no_sales_days';
    private const DEFAULT_THRESHOLD = 90;
    private const SAMPLE_LIMIT = 100;

    /**
     * Constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param ScopeConfigInterface $scopeConfig
     * @param AnalysisScope $analysisScope
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ScopeConfigInterface $scopeConfig,
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
        return 'no_sales';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Produtos sem Vendas';
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
        $days = (int) ($this->scopeConfig->getValue(self::CONFIG_THRESHOLD) ?? self::DEFAULT_THRESHOLD);

        try {
            $connection   = $this->resourceConnection->getConnection();
            $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
            $categoryLinkField = $this->metadataPool->getMetadata(CategoryInterface::class)->getLinkField();
            $productTable = $this->resourceConnection->getTableName('catalog_product_entity');
            $varcharTable = $this->resourceConnection->getTableName('catalog_product_entity_varchar');
            $decimalTable = $this->resourceConnection->getTableName('catalog_product_entity_decimal');
            $stockTable   = $this->resourceConnection->getTableName('cataloginventory_stock_item');
            $orderItem    = $this->resourceConnection->getTableName('sales_order_item');
            $eavTable     = $this->resourceConnection->getTableName('eav_attribute');
            $cpTable      = $this->resourceConnection->getTableName('catalog_category_product');
            $catVarchar   = $this->resourceConnection->getTableName('catalog_category_entity_varchar');
            $catEntity    = $this->resourceConnection->getTableName('catalog_category_entity');
            $storeFilter  = $this->analysisScope->storeFilterSql('oi.store_id');
            $productFilter = $this->analysisScope->productWebsiteFilterSql('p.entity_id');

            $sql = sprintf(
                "SELECT p.entity_id AS product_id, p.sku,
                           pname.value AS name,
                           pprice.value AS price,
                           pcost.value AS cost,
                           si.qty AS stock_qty,
                           MAX(oi.created_at) AS last_sale_date,
                           TIMESTAMPDIFF(DAY, MAX(oi.created_at), NOW()) AS days_without_sale,
                           COUNT(oi.item_id) AS total_sold_ever,
                           catname.value AS category
                    FROM {$productTable} p
                    LEFT JOIN {$varcharTable} pname
                        ON pname.{$linkField} = p.{$linkField}
                        AND pname.attribute_id = (
                            SELECT attribute_id FROM {$eavTable}
                            WHERE attribute_code='name' AND entity_type_id=4
                        )
                        AND pname.store_id = 0
                    LEFT JOIN {$decimalTable} pprice
                        ON pprice.{$linkField} = p.{$linkField}
                        AND pprice.attribute_id = (
                            SELECT attribute_id FROM {$eavTable}
                            WHERE attribute_code='price' AND entity_type_id=4
                        )
                        AND pprice.store_id = 0
                    LEFT JOIN {$decimalTable} pcost
                        ON pcost.{$linkField} = p.{$linkField}
                        AND pcost.attribute_id = (
                            SELECT attribute_id FROM {$eavTable}
                            WHERE attribute_code='cost' AND entity_type_id=4
                        )
                        AND pcost.store_id = 0
                    LEFT JOIN {$stockTable} si ON si.product_id = p.entity_id
                    LEFT JOIN {$orderItem} oi ON oi.product_id = p.entity_id{$storeFilter}
                    LEFT JOIN {$cpTable} cp ON cp.product_id = p.entity_id
                    LEFT JOIN {$catEntity} cce ON cce.entity_id = cp.category_id
                    LEFT JOIN {$catVarchar} catname
                        ON catname.{$categoryLinkField} = cce.{$categoryLinkField}
                        AND catname.attribute_id = (
                            SELECT attribute_id FROM {$eavTable}
                            WHERE attribute_code='name' AND entity_type_id=3
                        )
                        AND catname.store_id = 0
                    WHERE 1=1{$productFilter}
                    GROUP BY p.entity_id
                    HAVING (last_sale_date IS NULL OR days_without_sale >= %d)
                    AND (si.qty > 0 OR si.qty IS NULL)
                    ORDER BY days_without_sale DESC
                    LIMIT %d",
                $days,
                self::SAMPLE_LIMIT
            );

            $rows = $connection->fetchAll($sql);
        } catch (\Throwable $e) {
            return new CollectionResult(
                $this->getCode(),
                $this->getCategory(),
                'ok',
                ['count' => 0, 'threshold_days' => $days, 'note' => 'tables not accessible: ' . $e->getMessage()]
            );
        }

        $count = count($rows);
        $items = [];
        foreach ($rows as $row) {
            $productId       = (int) $row['product_id'];
            $totalSoldEver   = (int) $row['total_sold_ever'];
            $stockQty        = (float) ($row['stock_qty'] ?? 0);
            $daysWithoutSale = $row['days_without_sale'] !== null ? (int) $row['days_without_sale'] : null;

            $suggestion = $totalSoldEver > 0 ? 'bundle' : 'deactivate';

            $items[] = [
                'product_id'       => $productId,
                'sku'              => $row['sku'],
                'name'             => $row['name'],
                'price'            => $row['price'] !== null ? (float) $row['price'] : null,
                'cost'             => $row['cost'] !== null ? (float) $row['cost'] : null,
                'stock_qty'        => $stockQty,
                'last_sale_date'   => $row['last_sale_date'],
                'days_without_sale' => $daysWithoutSale,
                'total_sold_ever'  => $totalSoldEver,
                'category'         => $row['category'],
                'suggestion' => $suggestion,
            ];
        }

        $status = $count === 0 ? 'ok' : ($count < 20 ? 'warning' : 'critical');

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: [
                'count'          => $count,
                'threshold_days' => $days,
            ],
            items: $items
        );
    }
}

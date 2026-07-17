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

class PriceBelowCostCollector implements CollectorInterface
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
        return 'price_below_cost';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Produtos com Preço Abaixo do Custo';
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
            $decimalTable = $this->resourceConnection->getTableName('catalog_product_entity_decimal');
            $varcharTable = $this->resourceConnection->getTableName('catalog_product_entity_varchar');
            $eavTable     = $this->resourceConnection->getTableName('eav_attribute');

            $sql = "SELECT p.entity_id AS product_id, p.sku, pname.value AS name,
                           pprice.value AS price, pspecial.value AS special_price, pcost.value AS cost,
                           ROUND(COALESCE(pspecial.value, pprice.value) - pcost.value, 2) AS margin
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
                    LEFT JOIN {$decimalTable} pspecial
                        ON pspecial.{$linkField} = p.{$linkField}
                        AND pspecial.attribute_id = (
                            SELECT attribute_id FROM {$eavTable}
                            WHERE attribute_code='special_price' AND entity_type_id=4
                        )
                        AND pspecial.store_id = 0
                    LEFT JOIN {$decimalTable} pcost
                        ON pcost.{$linkField} = p.{$linkField}
                        AND pcost.attribute_id = (
                            SELECT attribute_id FROM {$eavTable}
                            WHERE attribute_code='cost' AND entity_type_id=4
                        )
                        AND pcost.store_id = 0
                    WHERE pcost.value IS NOT NULL
                    AND COALESCE(pspecial.value, pprice.value) < pcost.value
                    LIMIT 100";

            $rows = $connection->fetchAll($sql);
        } catch (\Throwable $e) {
            return new CollectionResult(
                $this->getCode(),
                $this->getCategory(),
                'ok',
                ['count' => 0, 'total_loss_per_sale' => 0.0,
                 'note' => 'catalog tables not accessible: ' . $e->getMessage()]
            );
        }

        $count        = count($rows);
        $totalLoss    = 0.0;
        $items        = [];

        foreach ($rows as $row) {
            $productId = (int) $row['product_id'];
            $margin    = (float) $row['margin'];
            $totalLoss += abs($margin);

            $items[] = [
                'product_id'    => $productId,
                'sku'           => $row['sku'],
                'name'          => $row['name'],
                'price'         => (float) $row['price'],
                'special_price' => $row['special_price'] !== null ? (float) $row['special_price'] : null,
                'cost'          => (float) $row['cost'],
                'margin'        => $margin,
            ];
        }

        $status = $count === 0 ? 'ok' : ($count < 5 ? 'warning' : 'critical');

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: [
                'count'               => $count,
                'total_loss_per_sale' => round($totalLoss, 2),
            ],
            items: $items
        );
    }
}

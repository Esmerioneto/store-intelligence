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

class ProductsMissingDescriptionCollector implements CollectorInterface
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
        return 'missing_description';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Produtos sem Descrição';
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
            $textTable    = $this->resourceConnection->getTableName('catalog_product_entity_text');
            $varcharTable = $this->resourceConnection->getTableName('catalog_product_entity_varchar');
            $eavTable     = $this->resourceConnection->getTableName('eav_attribute');

            $sql = "SELECT p.entity_id AS product_id, p.sku, pname.value AS name,
                           (pdesc.value IS NOT NULL AND pdesc.value != '') AS has_description,
                           (pshort.value IS NOT NULL AND pshort.value != '') AS has_short_description
                    FROM {$productTable} p
                    LEFT JOIN {$varcharTable} pname
                        ON pname.{$linkField} = p.{$linkField}
                        AND pname.attribute_id = (
                            SELECT attribute_id FROM {$eavTable}
                            WHERE attribute_code='name' AND entity_type_id=4
                        )
                        AND pname.store_id = 0
                    LEFT JOIN {$textTable} pdesc
                        ON pdesc.{$linkField} = p.{$linkField}
                        AND pdesc.attribute_id = (
                            SELECT attribute_id FROM {$eavTable}
                            WHERE attribute_code='description' AND entity_type_id=4
                        )
                        AND pdesc.store_id = 0
                    LEFT JOIN {$textTable} pshort
                        ON pshort.{$linkField} = p.{$linkField}
                        AND pshort.attribute_id = (
                            SELECT attribute_id FROM {$eavTable}
                            WHERE attribute_code='short_description' AND entity_type_id=4
                        )
                        AND pshort.store_id = 0
                    WHERE (pdesc.value IS NULL OR pdesc.value = '')
                       OR (pshort.value IS NULL OR pshort.value = '')
                    LIMIT 200";

            $rows = $connection->fetchAll($sql);
        } catch (\Throwable $e) {
            return new CollectionResult(
                $this->getCode(),
                $this->getCategory(),
                'ok',
                ['count' => 0, 'missing_both' => 0, 'note' => 'catalog tables not accessible: ' . $e->getMessage()]
            );
        }

        $count       = count($rows);
        $missingBoth = 0;
        $items       = [];

        foreach ($rows as $row) {
            $productId         = (int) $row['product_id'];
            $hasDesc           = (bool) $row['has_description'];
            $hasShortDesc      = (bool) $row['has_short_description'];

            if (!$hasDesc && !$hasShortDesc) {
                $missingBoth++;
            }

            $items[] = [
                'product_id'            => $productId,
                'sku'                   => $row['sku'],
                'name'                  => $row['name'],
                'has_description'       => $hasDesc,
                'has_short_description' => $hasShortDesc,
            ];
        }

        $status = $count === 0 ? 'ok' : ($count < 20 ? 'warning' : 'critical');

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: ['count' => $count, 'missing_both' => $missingBoth],
            items: $items
        );
    }
}

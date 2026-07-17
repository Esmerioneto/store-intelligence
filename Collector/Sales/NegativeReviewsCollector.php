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

class NegativeReviewsCollector implements CollectorInterface
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
        return 'negative_reviews';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Reviews Negativos';
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
            $reviewTable  = $this->resourceConnection->getTableName('review');
            $storeFilter  = $this->analysisScope->storeFilterSql('rd.store_id');
            $detailTable  = $this->resourceConnection->getTableName('review_detail');
            $ratingTable  = $this->resourceConnection->getTableName('rating_option_vote');
            $varcharTable = $this->resourceConnection->getTableName('catalog_product_entity_varchar');
            $eavTable     = $this->resourceConnection->getTableName('eav_attribute');
            $productTable = $this->resourceConnection->getTableName('catalog_product_entity');

            $sql = "SELECT r.review_id, r.entity_pk_value AS product_id,
                           pname.value AS product_name,
                           rd.title, r.created_at,
                           AVG(rov.value) AS rating
                    FROM {$reviewTable} r
                    JOIN {$detailTable} rd ON rd.review_id = r.review_id
                    LEFT JOIN {$ratingTable} rov ON rov.review_id = r.review_id
                    LEFT JOIN {$productTable} cpe ON cpe.entity_id = r.entity_pk_value
                    LEFT JOIN {$varcharTable} pname
                        ON pname.{$linkField} = cpe.{$linkField}
                        AND pname.attribute_id = (
                            SELECT attribute_id FROM {$eavTable}
                            WHERE attribute_code='name' AND entity_type_id=4
                        )
                        AND pname.store_id = 0
                    WHERE r.status_id = 1
                      AND r.created_at > DATE_SUB(NOW(), INTERVAL 90 DAY){$storeFilter}
                    GROUP BY r.review_id
                    HAVING rating <= 2
                    ORDER BY r.created_at DESC
                    LIMIT 50";

            $rows = $connection->fetchAll($sql);
        } catch (\Throwable $e) {
            return new CollectionResult(
                $this->getCode(),
                $this->getCategory(),
                'ok',
                ['count' => 0, 'avg_rating' => 0.0, 'note' => 'review tables not accessible: ' . $e->getMessage()]
            );
        }

        $count     = count($rows);
        $avgRating = $count > 0
            ? round(array_sum(array_column($rows, 'rating')) / $count, 2)
            : 0.0;

        $items = [];
        foreach ($rows as $row) {
            $reviewId = (int) $row['review_id'];
            $items[]  = [
                'review_id'    => $reviewId,
                'product_id'   => (int) $row['product_id'],
                'product_name' => $row['product_name'],
                'title'        => $row['title'],
                'rating'       => round((float) $row['rating'], 2),
                'created_at'   => $row['created_at'],
            ];
        }

        $status = $count === 0 ? 'ok' : ($count < 5 ? 'warning' : 'critical');

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: [
                'count'      => $count,
                'avg_rating' => $avgRating,
            ],
            items: $items
        );
    }
}

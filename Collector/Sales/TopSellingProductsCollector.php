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
use Egsn\StoreIntelligence\Model\AnalysisScope;
use Magento\Framework\App\ResourceConnection;

class TopSellingProductsCollector implements CollectorInterface
{
    /**
     * Constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param AnalysisScope $analysisScope
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly AnalysisScope $analysisScope
    ) {
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return 'top_selling';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Produtos Mais Vendidos';
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
            $connection  = $this->resourceConnection->getConnection();
            $orderItem   = $this->resourceConnection->getTableName('sales_order_item');
            $orderTable  = $this->resourceConnection->getTableName('sales_order');

            $sql = "SELECT oi.product_id, oi.sku, oi.name,
                           SUM(oi.qty_ordered) AS qty_sold,
                           SUM(oi.row_total) AS revenue,
                           ROUND(SUM(oi.row_total) / SUM(oi.qty_ordered), 2) AS avg_ticket
                    FROM {$orderItem} oi
                    JOIN {$orderTable} o ON o.entity_id = oi.order_id
                    WHERE o.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                    AND o.status NOT IN ('canceled','closed'){$this->analysisScope->storeFilterSql('o.store_id')}
                    GROUP BY oi.product_id, oi.sku, oi.name
                    ORDER BY revenue DESC
                    LIMIT 20";

            $rows = $connection->fetchAll($sql);
        } catch (\Throwable $e) {
            return new CollectionResult(
                $this->getCode(),
                $this->getCategory(),
                'ok',
                ['period_days' => 30, 'top_10_revenue' => 0.0,
                 'note' => 'sales tables not accessible: ' . $e->getMessage()]
            );
        }

        $items         = [];
        $top10Revenue  = 0.0;

        foreach ($rows as $index => $row) {
            $productId = (int) $row['product_id'];
            $revenue   = (float) $row['revenue'];

            if ($index < 10) {
                $top10Revenue += $revenue;
            }

            $items[] = [
                'product_id'    => $productId,
                'sku'           => $row['sku'],
                'name'          => $row['name'],
                'qty_sold'      => (float) $row['qty_sold'],
                'revenue'       => round($revenue, 2),
                'avg_ticket'    => (float) $row['avg_ticket'],
            ];
        }

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: 'ok',
            summary: [
                'period_days'    => 30,
                'top_10_revenue' => round($top10Revenue, 2),
            ],
            items: $items
        );
    }
}

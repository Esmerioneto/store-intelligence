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
use Egsn\StoreIntelligence\Model\AnalysisScope;
use Magento\Framework\App\ResourceConnection;

class CheckoutFunnelCollector implements CollectorInterface
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
        return 'checkout_funnel';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Funil de Checkout';
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
            $quoteTable  = $this->resourceConnection->getTableName('quote');
            $orderTable  = $this->resourceConnection->getTableName('sales_order');

            $quotesCreated = (int) $connection->fetchOne(
                "SELECT COUNT(*) FROM {$quoteTable}
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) AND items_count > 0
                 {$this->analysisScope->storeFilterSql('store_id')}
                 LIMIT 1"
            );

            $orderRow = $connection->fetchRow(
                "SELECT COUNT(*) AS cnt, SUM(grand_total) AS total
                 FROM {$orderTable}
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                 AND status NOT IN ('canceled','closed')
                 {$this->analysisScope->storeFilterSql('store_id')}
                 LIMIT 1"
            );

            $ordersPlaced = (int) ($orderRow['cnt'] ?? 0);
            $revenue      = (float) ($orderRow['total'] ?? 0.0);
        } catch (\Throwable $e) {
            return new CollectionResult(
                $this->getCode(),
                $this->getCategory(),
                'ok',
                ['quotes_created' => 0, 'orders_placed' => 0, 'conversion_rate' => 0.0,
                 'period_days' => 30, 'note' => 'tables not accessible: ' . $e->getMessage()]
            );
        }

        $conversionRate = $quotesCreated > 0
            ? round(($ordersPlaced / $quotesCreated) * 100, 2)
            : 0.0;

        $status = $conversionRate >= 60 ? 'ok' : ($conversionRate >= 40 ? 'warning' : 'critical');

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: [
                'quotes_created'  => $quotesCreated,
                'orders_placed'   => $ordersPlaced,
                'conversion_rate' => $conversionRate,
                'revenue_30d'     => round($revenue, 2),
                'period_days'     => 30,
            ]
        );
    }
}

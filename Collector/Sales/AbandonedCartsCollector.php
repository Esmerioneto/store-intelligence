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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;

class AbandonedCartsCollector implements CollectorInterface
{
    private const CONFIG_THRESHOLD = 'egsn_si/thresholds/abandoned_cart_hours';
    private const DEFAULT_THRESHOLD = 2;
    private const SAMPLE_LIMIT = 100;

    /**
     * Constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param ScopeConfigInterface $scopeConfig
     * @param AnalysisScope $analysisScope
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ScopeConfigInterface $scopeConfig,
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
        return 'abandoned_carts';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Carrinhos Abandonados';
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
        $hours = (int) ($this->scopeConfig->getValue(self::CONFIG_THRESHOLD) ?? self::DEFAULT_THRESHOLD);

        try {
            $connection = $this->resourceConnection->getConnection();
            $quoteTable = $this->resourceConnection->getTableName('quote');
            $orderTable = $this->resourceConnection->getTableName('sales_order');

            $sql = sprintf(
                "SELECT q.entity_id AS quote_id, q.items_count,
                        q.grand_total, q.updated_at
                 FROM {$quoteTable} q
                 WHERE q.items_count > 0 AND q.is_active = 1{$this->analysisScope->storeFilterSql('q.store_id')}
                 AND q.updated_at < DATE_SUB(NOW(), INTERVAL %d HOUR)
                 AND NOT EXISTS (
                     SELECT 1 FROM {$orderTable} so WHERE so.quote_id = q.entity_id
                 )
                 ORDER BY q.grand_total DESC
                 LIMIT %d",
                $hours,
                self::SAMPLE_LIMIT
            );

            $rows = $connection->fetchAll($sql);
        } catch (\Throwable $e) {
            return new CollectionResult(
                $this->getCode(),
                $this->getCategory(),
                'ok',
                ['count' => 0, 'total_value' => 0.0, 'threshold_hours' => $hours,
                 'note' => 'quote table not accessible: ' . $e->getMessage()]
            );
        }

        $count      = count($rows);
        $totalValue = array_sum(array_column($rows, 'grand_total'));

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'quote_id'    => (int) $row['quote_id'],
                'items_count' => (int) $row['items_count'],
                'grand_total' => (float) $row['grand_total'],
                'updated_at'  => $row['updated_at'],
            ];
        }

        $status = $count === 0 ? 'ok' : ($count < 10 ? 'warning' : 'critical');

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: [
                'count'           => $count,
                'total_value'     => round((float) $totalValue, 2),
                'threshold_hours' => $hours,
            ],
            items: $items
        );
    }
}

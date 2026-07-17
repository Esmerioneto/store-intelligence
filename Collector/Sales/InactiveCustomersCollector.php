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

class InactiveCustomersCollector implements CollectorInterface
{
    private const CONFIG_THRESHOLD = 'egsn_si/thresholds/inactive_customers_months';
    private const DEFAULT_THRESHOLD = 6;
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
        return 'inactive_customers';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Clientes Inativos';
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
        $months = (int) ($this->scopeConfig->getValue(self::CONFIG_THRESHOLD) ?? self::DEFAULT_THRESHOLD);

        try {
            $connection      = $this->resourceConnection->getConnection();
            $customerTable   = $this->resourceConnection->getTableName('customer_entity');
            $orderTable      = $this->resourceConnection->getTableName('sales_order');

            $sql = sprintf(
                "SELECT c.entity_id AS customer_id,
                        c.firstname, c.lastname, c.email,
                        MAX(o.created_at) AS last_order_date,
                        TIMESTAMPDIFF(MONTH, MAX(o.created_at), NOW()) AS months_inactive,
                        COUNT(o.entity_id) AS total_orders,
                        SUM(o.grand_total) AS lifetime_value
                 FROM {$customerTable} c
                 JOIN {$orderTable} o ON o.customer_id = c.entity_id
                 WHERE 1=1{$this->analysisScope->websiteFilterSql('c.website_id')}
                 GROUP BY c.entity_id, c.firstname, c.lastname, c.email
                 HAVING months_inactive >= %d
                 ORDER BY lifetime_value DESC
                 LIMIT %d",
                $months,
                self::SAMPLE_LIMIT
            );

            $rows = $connection->fetchAll($sql);
        } catch (\Throwable $e) {
            return new CollectionResult(
                $this->getCode(),
                $this->getCategory(),
                'ok',
                ['count' => 0, 'threshold_months' => $months, 'estimated_ltv' => 0.0,
                 'note' => 'customer/order tables not accessible: ' . $e->getMessage()]
            );
        }

        $count        = count($rows);
        $estimatedLtv = array_sum(array_column($rows, 'lifetime_value'));
        $items        = [];

        foreach ($rows as $row) {
            $items[] = [
                'customer_id'     => (int) $row['customer_id'],
                'name'            => trim($row['firstname'] . ' ' . $row['lastname']),
                'email'           => $row['email'],
                'last_order_date' => $row['last_order_date'],
                'months_inactive' => (int) $row['months_inactive'],
                'total_orders'    => (int) $row['total_orders'],
                'lifetime_value'  => round((float) $row['lifetime_value'], 2),
            ];
        }

        $status = $count === 0 ? 'ok' : ($count < 50 ? 'warning' : 'critical');

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: [
                'count'            => $count,
                'threshold_months' => $months,
                'estimated_ltv'    => round((float) $estimatedLtv, 2),
            ],
            items: $items
        );
    }
}

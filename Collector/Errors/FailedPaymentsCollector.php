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

namespace Egsn\StoreIntelligence\Collector\Errors;

use Egsn\StoreIntelligence\Collector\CollectionResult;
use Egsn\StoreIntelligence\Collector\CollectorInterface;
use Egsn\StoreIntelligence\Model\AnalysisScope;
use Magento\Framework\App\ResourceConnection;

class FailedPaymentsCollector implements CollectorInterface
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
        return 'failed_payments';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Pagamentos com Falha';
    }

    /**
     * Get category.
     *
     * @return string
     */
    public function getCategory(): string
    {
        return 'errors';
    }

    /**
     * Collect.
     *
     * @return CollectionResult
     */
    public function collect(): CollectionResult
    {
        $connection = $this->resourceConnection->getConnection();
        $table      = $this->resourceConnection->getTableName('sales_order');

        $paymentTable = $this->resourceConnection->getTableName('sales_order_payment');
        $storeFilter  = $this->analysisScope->storeFilterSql('o.store_id');
        $sql = "SELECT o.increment_id, o.status, o.grand_total, o.created_at,
                       p.method AS payment_method
                FROM {$table} o
                LEFT JOIN {$paymentTable} p ON p.parent_id = o.entity_id
                WHERE o.status IN ('payment_review', 'pending_payment')
                  AND o.created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR){$storeFilter}
                ORDER BY o.created_at DESC
                LIMIT 50";

        try {
            $rows = $connection->fetchAll($sql);
        } catch (\Throwable $e) {
            return new CollectionResult(
                $this->getCode(),
                $this->getCategory(),
                'ok',
                ['count' => 0, 'total_value' => 0.0, 'note' => 'sales_order not accessible: ' . $e->getMessage()]
            );
        }

        $count = count($rows);
        $total = array_sum(array_column($rows, 'grand_total'));

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'increment_id'   => $row['increment_id'],
                'status'         => $row['status'],
                'payment_method' => $row['payment_method'] ?? '—',
                'grand_total'    => (float) $row['grand_total'],
                'created_at'     => $row['created_at'],
            ];
        }

        $status = $count === 0 ? 'ok' : ($count < 10 ? 'warning' : 'critical');

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: ['count' => $count, 'total_value' => round((float) $total, 2)],
            items: $items,
            score: max(0, 100 - min(100, $count * 5))
        );
    }
}

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
use Magento\Framework\App\ResourceConnection;

class ExpiredCouponsCollector implements CollectorInterface
{
    /**
     * Constructor.
     *
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return 'expired_coupons';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Cupons Expirados Ativos';
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
            $ruleTable    = $this->resourceConnection->getTableName('salesrule');
            $couponTable  = $this->resourceConnection->getTableName('salesrule_coupon');

            $sql = "SELECT r.rule_id, r.name, sc.code AS coupon_code, r.to_date,
                           COALESCE(sc.times_used, 0) AS uses_total
                    FROM {$ruleTable} r
                    LEFT JOIN {$couponTable} sc ON sc.rule_id = r.rule_id
                    WHERE r.is_active = 1 AND r.to_date < CURDATE()
                    LIMIT 50";

            $rows = $connection->fetchAll($sql);
        } catch (\Throwable $e) {
            return new CollectionResult(
                $this->getCode(),
                $this->getCategory(),
                'ok',
                ['count' => 0, 'note' => 'salesrule tables not accessible: ' . $e->getMessage()]
            );
        }

        $count = count($rows);
        $items = [];
        foreach ($rows as $row) {
            $ruleId  = (int) $row['rule_id'];
            $items[] = [
                'rule_id'        => $ruleId,
                'name'           => $row['name'],
                'coupon_code'    => $row['coupon_code'],
                'to_date'        => $row['to_date'],
                'uses_total'     => (int) $row['uses_total'],
            ];
        }

        $status = $count === 0 ? 'ok' : ($count < 5 ? 'warning' : 'critical');

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: ['count' => $count],
            items: $items
        );
    }
}

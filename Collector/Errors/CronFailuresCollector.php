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
use Magento\Framework\App\ResourceConnection;

class CronFailuresCollector implements CollectorInterface
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
        return 'cron_failures';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Falhas de Cron';
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
        $table      = $this->resourceConnection->getTableName('cron_schedule');

        $sql = "SELECT job_code, status, COUNT(*) AS failures, MAX(messages) AS last_error
                FROM {$table}
                WHERE status IN ('error', 'missed')
                  AND scheduled_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY job_code, status
                ORDER BY failures DESC
                LIMIT 20";

        try {
            $rows = $connection->fetchAll($sql);
        } catch (\Throwable $e) {
            return new CollectionResult(
                $this->getCode(),
                $this->getCategory(),
                'ok',
                ['count' => 0, 'note' => 'cron_schedule not accessible: ' . $e->getMessage()]
            );
        }

        $jobs = [];
        foreach ($rows as $row) {
            $jobs[] = [
                'code'     => $row['job_code'],
                'failures' => (int) $row['failures'],
                'status'   => $row['status'],
            ];
        }

        $count  = count($jobs);
        $status = $count === 0 ? 'ok' : ($count < 5 ? 'warning' : 'critical');

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: ['count' => $count, 'jobs' => $jobs],
            score: max(0, 100 - min(100, $count * 5))
        );
    }
}

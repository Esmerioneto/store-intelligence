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

namespace Egsn\StoreIntelligence\Collector\Performance;

use Egsn\StoreIntelligence\Collector\CollectionResult;
use Egsn\StoreIntelligence\Collector\CollectorInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;

class SlowQueriesCollector implements CollectorInterface
{
    private const CONFIG_THRESHOLD = 'egsn_si/thresholds/slow_query_seconds';
    private const DEFAULT_THRESHOLD = 1;

    /**
     * Constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return 'slow_queries';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Slow Queries';
    }

    /**
     * Get category.
     *
     * @return string
     */
    public function getCategory(): string
    {
        return 'performance';
    }

    /**
     * Collect.
     *
     * @return CollectionResult
     */
    public function collect(): CollectionResult
    {
        $threshold = (float) ($this->scopeConfig->getValue(self::CONFIG_THRESHOLD) ?? self::DEFAULT_THRESHOLD);
        $count     = 0;
        $status    = 'ok';

        try {
            $connection = $this->resourceConnection->getConnection();
            $rows       = $connection->fetchAll(
                'SELECT COUNT(*) AS cnt FROM information_schema.processlist'
                . ' WHERE TIME >= :threshold AND COMMAND != :command',
                ['threshold' => (int) $threshold, 'command' => 'Sleep']
            );
            $count = (int) ($rows[0]['cnt'] ?? 0);

            if ($count >= 5) {
                $status = 'critical';
            } elseif ($count > 0) {
                $status = 'warning';
            }
        } catch (\Throwable $e) {
            // Table not accessible — not a blocking issue
            $count = 0;
        }

        $score = $count === 0 ? 100 : max(0, 100 - ($count * 10));

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: [
                'count'     => $count,
                'threshold' => $threshold,
            ],
            score: $score
        );
    }
}

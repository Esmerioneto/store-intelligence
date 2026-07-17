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
use Magento\Framework\App\Cache\Frontend\Pool;

class CacheHitRatioCollector implements CollectorInterface
{
    private const THRESHOLD = 85;

    /**
     * Constructor.
     *
     * @param Pool $cacheFrontendPool
     */
    public function __construct(
        private readonly Pool $cacheFrontendPool
    ) {
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return 'cache_hit_ratio';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Cache Hit Ratio';
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
        $backend = 'unknown';
        $hitRate = null;
        $status  = 'ok';

        try {
            $frontend       = $this->cacheFrontendPool->current();
            $backendAdapter = $frontend->getBackend();
            $backendClass   = get_class($backendAdapter);

            if (stripos($backendClass, 'Redis') !== false) {
                $backend = 'redis';
                $stats   = $backendAdapter->getStats();

                if (isset($stats['hits'], $stats['misses'])) {
                    $hits    = (int) $stats['hits'];
                    $misses  = (int) $stats['misses'];
                    $total   = $hits + $misses;
                    $hitRate = $total > 0 ? round(($hits / $total) * 100, 1) : null;

                    if ($hitRate !== null && $hitRate < self::THRESHOLD) {
                        $status = 'warning';
                    }
                }
            } elseif (stripos($backendClass, 'File') !== false) {
                $backend = 'file';
            }
        } catch (\Throwable $e) {
            // Redis not available or error — not a blocking issue
            $backend = 'unknown';
        }

        $score = $hitRate !== null ? (int) $hitRate : 100;

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: [
                'backend'   => $backend,
                'hit_rate'  => $hitRate,
                'threshold' => self::THRESHOLD,
            ],
            score: $score
        );
    }
}

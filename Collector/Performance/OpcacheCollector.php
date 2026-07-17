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

class OpcacheCollector implements CollectorInterface
{
    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return 'opcache';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'OPcache Hit Rate';
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
        $enabled = function_exists('opcache_get_status');
        $hitRate = null;
        $status  = 'ok';

        if ($enabled) {
            $data    = opcache_get_status(false) ?: [];
            $stats   = $data['opcache_statistics'] ?? [];
            $hits    = (int) ($stats['hits'] ?? 0);
            $misses  = (int) ($stats['misses'] ?? 0);
            $total   = $hits + $misses;
            $hitRate = $total > 0 ? round(($hits / $total) * 100, 1) : null;
            if ($hitRate !== null && $hitRate < 80) {
                $status = 'warning';
            }
        } else {
            $status = 'critical';
        }

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: ['enabled' => $enabled, 'hit_rate' => $hitRate],
            score: $hitRate !== null ? (int) $hitRate : ($enabled ? 50 : 0)
        );
    }
}

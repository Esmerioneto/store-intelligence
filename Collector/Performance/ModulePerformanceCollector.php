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
use Magento\Framework\Profiler;

class ModulePerformanceCollector implements CollectorInterface
{
    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return 'module_performance';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Module Performance Profiler';
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
        $profilerEnabled = false;

        try {
            // Profiler::isEnabled() does not exist in all Magento versions;
            // check via reflection or by inspecting the static drivers list.
            $reflection = new \ReflectionClass(Profiler::class);
            $property   = $reflection->getProperty('_enabled');
            $property->setAccessible(true);
            $profilerEnabled = (bool) $property->getValue();
        } catch (\Throwable $e) {
            // Unable to determine profiler state — assume disabled
            $profilerEnabled = false;
        }

        $note = $profilerEnabled
            ? 'Profiler is enabled. Disable in production for best performance.'
            : 'Profiler is disabled (recommended for production).';

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: 'ok',
            summary: [
                'profiler_enabled' => $profilerEnabled,
                'note'             => $note,
            ],
            score: 100
        );
    }
}

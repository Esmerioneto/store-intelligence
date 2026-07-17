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

namespace Egsn\StoreIntelligence\Model;

use Egsn\StoreIntelligence\Collector\CollectionResult;
use Egsn\StoreIntelligence\Collector\CollectorInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

class CollectorRunner
{
    private const CONFIG_TIMEOUT = 'egsn_si/thresholds/collector_timeout_%s';

    /**
     * Constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Run.
     *
     * @param CollectorInterface $collector
     * @return CollectionResult|null
     */
    public function run(CollectorInterface $collector): ?CollectionResult
    {
        $timeout = (float) $this->scopeConfig->getValue(
            sprintf(self::CONFIG_TIMEOUT, $collector->getCategory())
        );

        $start = microtime(true);

        try {
            $result = $collector->collect();

            // Post-hoc check only: PHP has no preemption, so the collector ran to completion.
            // If it exceeded the budget we discard the result rather than letting a slow collector
            // block the orchestrator indefinitely in future runs.
            if ($timeout > 0 && (microtime(true) - $start) > $timeout) {
                $this->logger->warning(sprintf(
                    '[StoreIntelligence] Collector %s timeout (%ss) — skipped',
                    $collector->getCode(),
                    $timeout
                ));
                return null;
            }

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                '[StoreIntelligence] Collector %s failed: %s',
                $collector->getCode(),
                $e->getMessage()
            ));
            return null;
        }
    }
}

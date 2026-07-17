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

namespace Egsn\StoreIntelligence\Cron;

use Egsn\StoreIntelligence\Model\WebhookNotifier;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class GarbageCollection
{
    /**
     * Constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param ResourceConnection $resource
     * @param LoggerInterface $logger
     * @param WebhookNotifier $webhookNotifier
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ResourceConnection   $resource,
        private readonly LoggerInterface      $logger,
        private readonly WebhookNotifier      $webhookNotifier
    ) {
    }

    /**
     * Execute.
     *
     * @return void
     */
    public function execute(): void
    {
        // Watchdog roda mesmo com cleanup desabilitado: análise travada é problema
        // de integridade, não de retenção de dados.
        $this->failStuckAnalyses();

        if (!$this->scopeConfig->getValue('egsn_si/cleanup/enabled')) {
            return;
        }

        $days = (int) ($this->scopeConfig->getValue('egsn_si/cleanup/retention_days') ?: 90);
        $conn = $this->resource->getConnection();

        $deleted = $conn->delete(
            $this->resource->getTableName('egsn_si_analysis'),
            ['created_at < DATE_SUB(NOW(), INTERVAL ? DAY)' => $days]
        );

        // ponytail: expired locks are always junk; retention_days doesn't apply here
        $conn->delete(
            $this->resource->getTableName('egsn_si_lock'),
            ['expires_at < NOW()']
        );

        $this->logger->info("StoreIntelligence GC: removed {$deleted} analyses older than {$days} days.");
    }

    /**
     * Marca como failed análises presas em "running" além do timeout global
     *
     * (consumer morto no meio do processamento deixa a linha órfã).
     *
     * @return void
     */
    private function failStuckAnalyses(): void
    {
        $timeout = (int) ($this->scopeConfig->getValue('egsn_si/thresholds/global_timeout_minutes') ?: 10);
        $conn    = $this->resource->getConnection();
        $table   = $this->resource->getTableName('egsn_si_analysis');

        // ran_at é gravado em UTC (gmtDate); NOW() dependeria do fuso do servidor
        $stuckIds = array_map('intval', $conn->fetchCol(
            $conn->select()
                ->from($table, ['id'])
                ->where('status = ?', 'running')
                ->where("ran_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$timeout} MINUTE)")
        ));
        if (empty($stuckIds)) {
            return;
        }

        $conn->update(
            $table,
            [
                'status'        => 'failed',
                'error_message' => "Watchdog: análise excedeu o timeout global de {$timeout} min"
                    . ' sem concluir (consumer interrompido).',
                'finished_at'   => new \Zend_Db_Expr('UTC_TIMESTAMP()'),
            ],
            ['id IN (?)' => $stuckIds]
        );

        $this->logger->warning(
            'StoreIntelligence watchdog: ' . count($stuckIds) . ' analysis(es) stuck in running marked as failed.'
        );
        foreach ($stuckIds as $stuckId) {
            $this->webhookNotifier->send($stuckId);
        }
    }
}

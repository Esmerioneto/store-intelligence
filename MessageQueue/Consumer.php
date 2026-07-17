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

namespace Egsn\StoreIntelligence\MessageQueue;

use Egsn\StoreIntelligence\Model\EmailNotifier;
use Egsn\StoreIntelligence\Model\Exception\AnalysisFailedException;
use Egsn\StoreIntelligence\Model\Orchestrator;
use Egsn\StoreIntelligence\Model\WebhookNotifier;
use Psr\Log\LoggerInterface;

class Consumer
{
    /**
     * Constructor.
     *
     * @param Orchestrator $orchestrator
     * @param LoggerInterface $logger
     * @param EmailNotifier $emailNotifier
     * @param WebhookNotifier $webhookNotifier
     */
    public function __construct(
        private readonly Orchestrator    $orchestrator,
        private readonly LoggerInterface $logger,
        private readonly EmailNotifier   $emailNotifier,
        private readonly WebhookNotifier $webhookNotifier
    ) {
    }

    /**
     * Process.
     *
     * @param string $message
     * @return void
     */
    public function process(string $message): void
    {
        $data        = json_decode($message, true);
        $triggeredBy = $data['triggered_by'] ?? 'queue';

        try {
            $id = $this->orchestrator->run($triggeredBy);
            $this->logger->info("[StoreIntelligence] Analysis completed. ID: {$id}");

            if ($id !== null) {
                $this->emailNotifier->send($id);
                $this->webhookNotifier->send($id);
            }
        } catch (AnalysisFailedException $e) {
            $this->logger->error("[StoreIntelligence] Analysis failed: {$e->getMessage()}");
            $this->webhookNotifier->send($e->getAnalysisId());
        } catch (\Throwable $e) {
            $this->logger->error("[StoreIntelligence] Analysis failed: {$e->getMessage()}");
        }
    }
}

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

use GuzzleHttp\Client;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Notificação via webhook genérico. O payload {"text": "..."} é aceito por
 * Slack e Microsoft Teams (incoming webhooks) e por endpoints próprios.
 */
class WebhookNotifier
{
    /**
     * Constructor.
     *
     * @param Client $client
     * @param ScopeConfigInterface $scopeConfig
     * @param ResourceConnection $resource
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Client               $client,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ResourceConnection   $resource,
        private readonly LoggerInterface      $logger
    ) {
    }

    /**
     * Send.
     *
     * @param int $analysisId
     * @return void
     */
    public function send(int $analysisId): void
    {
        if (!$this->scopeConfig->getValue('egsn_si/webhook/enabled')) {
            return;
        }
        $url = trim((string) $this->scopeConfig->getValue('egsn_si/webhook/url'));
        if ($url === '') {
            return;
        }

        try {
            $conn     = $this->resource->getConnection();
            $analysis = $conn->fetchRow(
                $conn->select()
                    ->from($this->resource->getTableName('egsn_si_analysis'))
                    ->where('id = ?', $analysisId)
            );
            if (!$analysis) {
                return;
            }

            if (($analysis['status'] ?? '') === 'failed') {
                $text = "Store Intelligence: análise #{$analysisId} FALHOU.";
                if (!empty($analysis['error_message'])) {
                    $text .= "\n" . $analysis['error_message'];
                }
            } else {
                $critical = (int) $conn->fetchOne(
                    $conn->select()
                        ->from($this->resource->getTableName('egsn_si_recommendation'), [new \Zend_Db_Expr('COUNT(*)')])
                        ->where('analysis_id = ?', $analysisId)
                        ->where('priority = ?', 'critical')
                );

                $score = $analysis['score'] !== null ? $analysis['score'] . '/100' : 'N/A';
                $text  = "Store Intelligence: análise #{$analysisId} concluída — score {$score}"
                    . ($critical > 0 ? ", {$critical} recomendação(ões) crítica(s)" : '')
                    . '.';
                if (!empty($analysis['summary'])) {
                    $text .= "\n" . $analysis['summary'];
                }
            }

            $response = $this->client->post($url, [
                'headers'     => ['content-type' => 'application/json'],
                'body'        => json_encode(['text' => $text], JSON_UNESCAPED_UNICODE),
                'http_errors' => false,
                'timeout'     => 10,
            ]);

            if ($response->getStatusCode() >= 400) {
                $this->logger->warning(sprintf(
                    '[StoreIntelligence] Webhook returned HTTP %d for analysis #%d.',
                    $response->getStatusCode(),
                    $analysisId
                ));
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[StoreIntelligence] Webhook notification failed: ' . $e->getMessage());
        }
    }
}

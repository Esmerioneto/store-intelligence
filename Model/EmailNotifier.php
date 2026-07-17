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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Escaper;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

class EmailNotifier
{
    private const TEMPLATE_ID = 'egsn_si_analysis_report';

    /**
     * Constructor.
     *
     * @param TransportBuilder $transportBuilder
     * @param ScopeConfigInterface $scopeConfig
     * @param ResourceConnection $resource
     * @param DateTime $dateTime
     * @param Escaper $escaper
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly TransportBuilder     $transportBuilder,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ResourceConnection   $resource,
        private readonly DateTime             $dateTime,
        private readonly Escaper              $escaper,
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
        if (!$this->scopeConfig->getValue('egsn_si/email/enabled')) {
            return;
        }

        $recipientsRaw = $this->scopeConfig->getValue('egsn_si/email/recipients') ?? '';
        $recipients    = array_filter(array_map('trim', explode("\n", $recipientsRaw)));
        if (empty($recipients)) {
            return;
        }

        try {
            $conn     = $this->resource->getConnection();
            $aTable   = $this->resource->getTableName('egsn_si_analysis');
            $recTable = $this->resource->getTableName('egsn_si_recommendation');

            $analysis = $conn->fetchRow("SELECT * FROM {$aTable} WHERE id = ?", [$analysisId]);
            if (!$analysis) {
                return;
            }

            $recs = $conn->fetchAll(
                "SELECT * FROM {$recTable} WHERE analysis_id = ? AND priority = 'critical' LIMIT 20",
                [$analysisId]
            );

            $recsHtml = '';
            foreach ($recs as $rec) {
                $recsHtml .= "<li><strong>" . $this->escaper->escapeHtml($rec['title'] ?? '') . "</strong>: "
                           . $this->escaper->escapeHtml($rec['action'] ?? '') . "</li>";
            }

            $adminPath = (string) ($this->scopeConfig->getValue('admin/url/custom_path') ?: 'admin');
            $baseUrl   = (string) ($this->scopeConfig->getValue('web/secure/base_url')
                       ?: $this->scopeConfig->getValue('web/unsecure/base_url'));

            $templateVars = [
                'analysis_score'   => $analysis['score'] ?? 'N/A',
                'analysis_summary' => $this->escaper->escapeHtml((string) ($analysis['summary'] ?? '')),
                'recommendations'  => $recsHtml,
                'store_url'        => rtrim($baseUrl, '/') . '/' . $adminPath . '/egsn_si/recommendations/',
            ];

            $logTable = $this->resource->getTableName('egsn_si_email_log');

            foreach ($recipients as $email) {
                try {
                    $transport = $this->transportBuilder
                        ->setTemplateIdentifier(self::TEMPLATE_ID)
                        ->setTemplateOptions(['area' => 'adminhtml', 'store' => 0])
                        ->setTemplateVars($templateVars)
                        ->setFromByScope('general')
                        ->addTo($email)
                        ->getTransport();

                    $transport->sendMessage();

                    $conn->insert($logTable, [
                        'analysis_id' => $analysisId,
                        'recipient'   => $email,
                        'sent_at'     => $this->dateTime->gmtDate('Y-m-d H:i:s'),
                        'status'      => 'sent',
                    ]);
                } catch (\Throwable) {
                    $conn->insert($logTable, [
                        'analysis_id' => $analysisId,
                        'recipient'   => $email,
                        'sent_at'     => $this->dateTime->gmtDate('Y-m-d H:i:s'),
                        'status'      => 'failed',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning(
                'StoreIntelligence email notification failed',
                ['exception' => $e->getMessage()]
            );
        }
    }
}

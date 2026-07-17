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
use GuzzleHttp\Client;
use Magento\Framework\App\Config\ScopeConfigInterface;

class PageLoadTimeCollector implements CollectorInterface
{
    private const THRESHOLD_MS     = 3000;
    private const WARNING_LIMIT_MS = 5000;
    private const BASE_URL_CONFIG  = 'web/unsecure/base_url';

    /**
     * Constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param Client $client
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Client               $client
    ) {
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return 'page_load_time';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Page Load Time';
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
        $baseUrl    = (string) $this->scopeConfig->getValue(self::BASE_URL_CONFIG);
        $ms         = 0;
        $statusCode = 0;
        $status     = 'ok';

        if (empty($baseUrl)) {
            return new CollectionResult(
                collectorCode: $this->getCode(),
                category: $this->getCategory(),
                status: 'ok',
                summary: [
                    'url'          => '',
                    'ms'           => 0,
                    'threshold_ms' => self::THRESHOLD_MS,
                    'status_code'  => 0,
                    'note'         => 'Base URL not configured',
                ],
                score: 100
            );
        }

        try {
            $start    = microtime(true);
            $response = $this->client->get($baseUrl, [
                'timeout'         => 10,
                'allow_redirects' => ['max' => 3],
                'http_errors'     => false,
                // ponytail: mede tempo de carga da própria loja; certificado
                // self-signed (dev) não deve impedir a medição
                'verify'          => false,
            ]);
            $elapsed = microtime(true) - $start;

            $ms         = (int) round($elapsed * 1000);
            $statusCode = $response->getStatusCode();

            if ($ms > self::WARNING_LIMIT_MS) {
                $status = 'critical';
            } elseif ($ms > self::THRESHOLD_MS) {
                $status = 'warning';
            }
        } catch (\Throwable $e) {
            return new CollectionResult(
                collectorCode: $this->getCode(),
                category: $this->getCategory(),
                status: 'ok',
                summary: [
                    'url'          => $baseUrl,
                    'ms'           => 0,
                    'threshold_ms' => self::THRESHOLD_MS,
                    'status_code'  => 0,
                    'note'         => 'Could not measure page load time: ' . $e->getMessage(),
                ],
                score: 100
            );
        }

        $score = $ms === 0 ? 100 : max(0, (int) round((1 - ($ms / self::WARNING_LIMIT_MS)) * 100));

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: [
                'url'          => $baseUrl,
                'ms'           => $ms,
                'threshold_ms' => self::THRESHOLD_MS,
                'status_code'  => $statusCode,
            ],
            score: $score
        );
    }
}

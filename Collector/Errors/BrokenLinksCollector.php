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

// phpcs:disable Magento2.SQL.RawQuery.FoundRawSql -- SQL raw deliberado: só inteiros e nomes de tabela interpolados

namespace Egsn\StoreIntelligence\Collector\Errors;

use Egsn\StoreIntelligence\Collector\CollectionResult;
use Egsn\StoreIntelligence\Collector\CollectorInterface;
use GuzzleHttp\Client;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;

class BrokenLinksCollector implements CollectorInterface
{
    private const MAX_URLS_TO_CHECK = 10;

    /**
     * Constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param Client $client
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ResourceConnection   $resourceConnection,
        private readonly Client               $client,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface      $logger
    ) {
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return 'broken_links';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Links Quebrados';
    }

    /**
     * Get category.
     *
     * @return string
     */
    public function getCategory(): string
    {
        return 'errors';
    }

    /**
     * Collect.
     *
     * @return CollectionResult
     */
    public function collect(): CollectionResult
    {
        $baseUrl = $this->scopeConfig->getValue(
            'web/secure/base_url',
            ScopeInterface::SCOPE_STORE
        ) ?? $this->scopeConfig->getValue('web/unsecure/base_url', ScopeInterface::SCOPE_STORE) ?? '';

        $urls = $this->collectUrls($baseUrl);

        $checked = 0;
        $broken  = 0;
        $items   = [];

        foreach (array_slice($urls, 0, self::MAX_URLS_TO_CHECK) as [$url, $foundIn]) {
            try {
                $response   = $this->client->head($url, [
                    'allow_redirects' => true,
                    'timeout'         => 5,
                    'http_errors'     => false,
                ]);
                $statusCode = $response->getStatusCode();
                $checked++;

                if ($statusCode >= 400) {
                    $broken++;
                    $items[] = [
                        'url'         => $url,
                        'status_code' => $statusCode,
                        'found_in'    => $foundIn,
                    ];
                }
            } catch (\Throwable $e) {
                $checked++;
                $broken++;
                $items[] = [
                    'url'         => $url,
                    'status_code' => 0,
                    'found_in'    => $foundIn,
                ];
            }
        }

        $status = $broken === 0 ? 'ok' : 'warning';

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: ['checked' => $checked, 'broken' => $broken],
            items: $items,
            score: $checked > 0 ? (int) round((($checked - $broken) / $checked) * 100) : 100
        );
    }

    /**
     * Collect urls.
     *
     * @param string $baseUrl
     * @return array
     */
    private function collectUrls(string $baseUrl): array
    {
        $urls       = [];
        $connection = $this->resourceConnection->getConnection();

        try {
            $cmsTable = $this->resourceConnection->getTableName('cms_page');
            $sql      = "SELECT identifier FROM {$cmsTable} WHERE is_active = 1 LIMIT 20";
            $rows     = $connection->fetchAll($sql);

            foreach ($rows as $row) {
                $url = rtrim($baseUrl, '/') . '/' . ltrim($row['identifier'], '/');
                if ($baseUrl && str_starts_with($url, $baseUrl)) {
                    $urls[] = [$url, 'cms_page'];
                }
            }
        } catch (\Throwable $e) {
            $this->logger->debug('[StoreIntelligence] broken_links: CMS pages skipped: ' . $e->getMessage());
        }

        return $urls;
    }
}

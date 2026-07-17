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

namespace Egsn\StoreIntelligence\Collector\Sales;

use Egsn\StoreIntelligence\Collector\CollectionResult;
use Egsn\StoreIntelligence\Collector\CollectorInterface;
use Egsn\StoreIntelligence\Model\AnalysisScope;
use Magento\Framework\App\ResourceConnection;

class ZeroResultSearchesCollector implements CollectorInterface
{
    /**
     * Constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param AnalysisScope $analysisScope
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly AnalysisScope $analysisScope
    ) {
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return 'zero_result_searches';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Buscas sem Resultado';
    }

    /**
     * Get category.
     *
     * @return string
     */
    public function getCategory(): string
    {
        return 'sales';
    }

    /**
     * Collect.
     *
     * @return CollectionResult
     */
    public function collect(): CollectionResult
    {
        try {
            $connection  = $this->resourceConnection->getConnection();
            $searchTable = $this->resourceConnection->getTableName('search_query');
            $storeFilter = $this->analysisScope->storeFilterSql('store_id');

            $sql = "SELECT query_text AS term, popularity AS searches
                    FROM {$searchTable}
                    WHERE num_results = 0
                      AND updated_at > DATE_SUB(NOW(), INTERVAL 7 DAY){$storeFilter}
                    ORDER BY popularity DESC
                    LIMIT 50";

            $rows = $connection->fetchAll($sql);
        } catch (\Throwable $e) {
            return new CollectionResult(
                $this->getCode(),
                $this->getCategory(),
                'ok',
                ['count' => 0, 'top_terms' => [], 'note' => 'search_query table not accessible: ' . $e->getMessage()]
            );
        }

        $count    = count($rows);
        $topTerms = [];
        foreach ($rows as $row) {
            $topTerms[] = [
                'term'    => $row['term'],
                'searches' => (int) $row['searches'],
            ];
        }

        $status = $count === 0 ? 'ok' : ($count < 20 ? 'warning' : 'critical');

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: [
                'count'     => $count,
                'top_terms' => array_slice($topTerms, 0, 5),
            ],
            items: $topTerms
        );
    }
}

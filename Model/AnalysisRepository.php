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

use Egsn\StoreIntelligence\Api\AnalysisRepositoryInterface;
use Egsn\StoreIntelligence\Api\Data\AnalysisInterface;
use Egsn\StoreIntelligence\Model\Data\Analysis;
use Egsn\StoreIntelligence\Model\Data\Recommendation;
use Egsn\StoreIntelligence\Model\Export\CsvExporter;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use Zend_Db_Expr;

class AnalysisRepository implements AnalysisRepositoryInterface
{
    /**
     * Constructor.
     *
     * @param ResourceConnection $resource
     * @param CsvExporter $csvExporter
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly CsvExporter        $csvExporter,
        private readonly DateTime           $dateTime,
        private readonly LoggerInterface    $logger
    ) {
    }

    /**
     * Get list.
     *
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function getList(int $page = 1, int $pageSize = 20): array
    {
        $page     = max(1, $page);
        $pageSize = max(1, min(200, $pageSize));
        try {
            $conn   = $this->resource->getConnection();
            $table  = $this->resource->getTableName('egsn_si_analysis');
            $offset = ($page - 1) * $pageSize;

            $total = (int) $conn->fetchOne(
                $conn->select()->from($table, [new Zend_Db_Expr('COUNT(*)')])
            );
            $rows  = $conn->fetchAll(
                $conn->select()
                    ->from($table, [
                        'id', 'status', 'triggered_by', 'website_id', 'score', 'ran_at',
                        'finished_at', 'ai_provider', 'error_message', 'created_at',
                    ])
                    ->order('id DESC')
                    ->limit($pageSize, $offset)
            );
            return ['items' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
        } catch (\Throwable $e) {
            $this->logger->warning('[StoreIntelligence] getList failed: ' . $e->getMessage());
            return ['items' => [], 'total' => 0, 'page' => $page, 'page_size' => $pageSize];
        }
    }

    /**
     * Get latest.
     *
     * @return AnalysisInterface
     */
    public function getLatest(): AnalysisInterface
    {
        try {
            $conn  = $this->resource->getConnection();
            $table = $this->resource->getTableName('egsn_si_analysis');
            $row   = $conn->fetchRow(
                $conn->select()->from($table)->where('status = ?', 'completed')->order('id DESC')->limit(1)
            );
        } catch (\Throwable $e) {
            $this->logger->warning('[StoreIntelligence] getLatest failed: ' . $e->getMessage());
            $row = false;
        }
        if (!$row) {
            throw new NoSuchEntityException(__('No completed analysis found'));
        }
        return new Analysis($row);
    }

    /**
     * Get by id.
     *
     * @param int $id
     * @return AnalysisInterface
     */
    public function getById(int $id): AnalysisInterface
    {
        try {
            $conn  = $this->resource->getConnection();
            $table = $this->resource->getTableName('egsn_si_analysis');
            $row   = $conn->fetchRow($conn->select()->from($table)->where('id = ?', $id));
            if (!$row) {
                throw new NoSuchEntityException(__('Analysis with id %1 not found', $id));
            }
            $recTable = $this->resource->getTableName('egsn_si_recommendation');
            $recs     = array_map(
                static fn (array $rec): Recommendation => new Recommendation($rec),
                $conn->fetchAll($conn->select()->from($recTable)->where('analysis_id = ?', $id))
            );
            return new Analysis($row, $recs);
        } catch (NoSuchEntityException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new NoSuchEntityException(__('Analysis with id %1 not found', $id));
        }
    }

    /**
     * Get performance diagnostics.
     *
     * @return array
     */
    public function getPerformanceDiagnostics(): array
    {
        return $this->getDiagnosticsByCategory('performance');
    }

    /**
     * Get errors diagnostics.
     *
     * @return array
     */
    public function getErrorsDiagnostics(): array
    {
        return $this->getDiagnosticsByCategory('errors');
    }

    /**
     * Get sales diagnostics.
     *
     * @return array
     */
    public function getSalesDiagnostics(): array
    {
        return $this->getDiagnosticsByCategory('sales');
    }

    /**
     * Get recommendations.
     *
     * @param string $priority
     * @param string $category
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function getRecommendations(
        string $priority = '',
        string $category = '',
        int $page = 1,
        int $pageSize = 20
    ): array {
        $page     = max(1, $page);
        $pageSize = max(1, min(200, $pageSize));
        try {
            $conn   = $this->resource->getConnection();
            $table  = $this->resource->getTableName('egsn_si_recommendation');
            $offset = ($page - 1) * $pageSize;

            $countSelect = $conn->select()
                ->from($table, [new Zend_Db_Expr('COUNT(*)')])
                ->where('dismissed = 0');
            $rowsSelect  = $conn->select()
                ->from($table)
                ->where('dismissed = 0')
                ->order(new Zend_Db_Expr("CASE priority WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END"))
                ->order('id DESC')
                ->limit($pageSize, $offset);
            if ($priority !== '') {
                $countSelect->where('priority = ?', $priority);
                $rowsSelect->where('priority = ?', $priority);
            }
            if ($category !== '') {
                $countSelect->where('category = ?', $category);
                $rowsSelect->where('category = ?', $category);
            }

            $total = (int) $conn->fetchOne($countSelect);
            $rows  = $conn->fetchAll($rowsSelect);
            return ['items' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
        } catch (\Throwable $e) {
            $this->logger->error('[StoreIntelligence] getRecommendations failed: ' . $e->getMessage());
            return ['items' => [], 'total' => 0, 'page' => $page, 'page_size' => $pageSize];
        }
    }

    /**
     * Get recommendation by id.
     *
     * @param int $id
     * @return array
     */
    public function getRecommendationById(int $id): array
    {
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName('egsn_si_recommendation');
        $row   = $conn->fetchRow($conn->select()->from($table)->where('id = ?', $id));
        if (!$row) {
            throw new NoSuchEntityException(
                __('Recommendation with ID %1 not found.', $id)
            );
        }
        return $row;
    }

    /**
     * Dismiss recommendation.
     *
     * @param int $id
     * @return bool
     */
    public function dismissRecommendation(int $id): bool
    {
        try {
            $conn  = $this->resource->getConnection();
            $table = $this->resource->getTableName('egsn_si_recommendation');
            $affected = $conn->update(
                $table,
                ['dismissed' => 1, 'dismissed_at' => $this->dateTime->gmtDate('Y-m-d H:i:s')],
                ['id = ?' => $id]
            );
            return $affected > 0;
        } catch (\Throwable $e) {
            $this->logger->warning('[StoreIntelligence] dismissRecommendation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get collectors.
     *
     * @return array
     */
    public function getCollectors(): array
    {
        try {
            $conn    = $this->resource->getConnection();
            $crTable = $this->resource->getTableName('egsn_si_collector_result');
            $aTable  = $this->resource->getTableName('egsn_si_analysis');

            $latestSelect = $conn->select()
                ->from($aTable, ['id'])
                ->where('status = ?', 'completed')
                ->order('id DESC')
                ->limit(1);

            return $conn->fetchAll(
                $conn->select()
                    ->from(
                        ['cr' => $crTable],
                        ['code' => 'collector', 'category', 'status', 'score', 'analysis_id']
                    )
                    ->join(['a' => $aTable], 'a.id = cr.analysis_id', ['last_run' => 'ran_at'])
                    ->where('cr.analysis_id = ?', $latestSelect)
                    ->order(['cr.category', 'cr.collector'])
            );
        } catch (\Throwable $e) {
            $this->logger->warning('[StoreIntelligence] getCollectors failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get collector result.
     *
     * @param string $code
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function getCollectorResult(string $code, int $page = 1, int $pageSize = 50): array
    {
        $page     = max(1, $page);
        $pageSize = max(1, min(200, $pageSize));
        try {
            $conn    = $this->resource->getConnection();
            $crTable = $this->resource->getTableName('egsn_si_collector_result');
            $aTable  = $this->resource->getTableName('egsn_si_analysis');
            $offset  = ($page - 1) * $pageSize;

            $rows = $conn->fetchAll(
                $conn->select()
                    ->from(['cr' => $crTable])
                    ->join(['a' => $aTable], 'a.id = cr.analysis_id', ['ran_at'])
                    ->where('cr.collector = ?', $code)
                    ->where('a.status = ?', 'completed')
                    ->order('cr.analysis_id DESC')
                    ->limit($pageSize, $offset)
            );
            foreach ($rows as &$row) {
                $row['data'] = json_decode($row['data'], true);
            }
            return ['items' => $rows, 'page' => $page, 'page_size' => $pageSize];
        } catch (\Throwable $e) {
            $this->logger->warning('[StoreIntelligence] getCollectorResult failed: ' . $e->getMessage());
            return ['items' => [], 'page' => $page, 'page_size' => $pageSize];
        }
    }

    /**
     * Export analysis.
     *
     * @param int $id
     * @return string
     */
    public function exportAnalysis(int $id): string
    {
        return $this->csvExporter->export($id);
    }

    /**
     * Get comparison.
     *
     * @return array
     */
    /**
     * Get analysis results.
     *
     * @param int $analysisId
     * @return array
     */
    public function getAnalysisResults(int $analysisId): array
    {
        try {
            $conn = $this->resource->getConnection();
            $rows = $conn->fetchAll(
                $conn->select()
                    ->from($this->resource->getTableName('egsn_si_collector_result'))
                    ->where('analysis_id = ?', $analysisId)
                    ->order(['category', 'collector'])
            );
            foreach ($rows as &$row) {
                $row['data'] = json_decode((string) $row['data'], true) ?: [];
            }
            return $rows;
        } catch (\Throwable $e) {
            $this->logger->warning('[StoreIntelligence] getAnalysisResults failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get monthly token usage.
     *
     * @return int
     */
    public function getMonthlyTokenUsage(): int
    {
        try {
            $conn = $this->resource->getConnection();
            return (int) $conn->fetchOne(
                $conn->select()
                    ->from($this->resource->getTableName('egsn_si_analysis'), [
                        new Zend_Db_Expr('COALESCE(SUM(ai_tokens), 0)'),
                    ])
                    ->where('created_at >= ?', $this->dateTime->gmtDate('Y-m-01 00:00:00'))
            );
        } catch (\Throwable $e) {
            $this->logger->warning('[StoreIntelligence] getMonthlyTokenUsage failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get comparison.
     *
     * @return array
     */
    public function getComparison(): array
    {
        $empty = ['new' => [], 'resolved' => [], 'persistent' => [], 'current_id' => null, 'previous_id' => null];
        try {
            $conn    = $this->resource->getConnection();
            $aTable  = $this->resource->getTableName('egsn_si_analysis');
            $crTable = $this->resource->getTableName('egsn_si_collector_result');

            $ids = array_map('intval', $conn->fetchCol(
                $conn->select()->from($aTable, ['id'])->where('status = ?', 'completed')->order('id DESC')->limit(2)
            ));
            if (count($ids) < 2) {
                return $empty;
            }
            [$currentId, $previousId] = $ids;

            $statusByCollector = fn (int $id): array => $conn->fetchPairs(
                $conn->select()->from($crTable, ['collector', 'status'])->where('analysis_id = ?', $id)
            );
            $current  = $statusByCollector($currentId);
            $previous = $statusByCollector($previousId);

            $isProblem = static fn (?string $s): bool => in_array($s, ['warning', 'critical'], true);

            $result = ['new' => [], 'resolved' => [], 'persistent' => [],
                'current_id' => $currentId, 'previous_id' => $previousId];
            foreach ($current as $collector => $status) {
                $before = $previous[$collector] ?? null;
                if ($isProblem($status) && !$isProblem($before)) {
                    $result['new'][$collector] = $status;
                } elseif ($isProblem($status) && $isProblem($before)) {
                    $result['persistent'][$collector] = $status;
                }
            }
            foreach ($previous as $collector => $status) {
                if ($isProblem($status) && !$isProblem($current[$collector] ?? null)) {
                    $result['resolved'][$collector] = $status;
                }
            }
            return $result;
        } catch (\Throwable $e) {
            $this->logger->warning('[StoreIntelligence] getComparison failed: ' . $e->getMessage());
            return $empty;
        }
    }

    /**
     * Get diagnostics by category.
     *
     * @param string $category
     * @return array
     */
    private function getDiagnosticsByCategory(string $category): array
    {
        try {
            $conn    = $this->resource->getConnection();
            $crTable = $this->resource->getTableName('egsn_si_collector_result');
            $aTable  = $this->resource->getTableName('egsn_si_analysis');

            $latestSelect = $conn->select()
                ->from($aTable, ['id'])
                ->where('status = ?', 'completed')
                ->order('id DESC')
                ->limit(1);

            $rows = $conn->fetchAll(
                $conn->select()
                    ->from(['cr' => $crTable], ['code' => 'collector', 'status', 'score', 'data'])
                    ->where('cr.category = ?', $category)
                    ->where('cr.analysis_id = ?', $latestSelect)
                    ->order('cr.status DESC')
                    ->order('cr.score ASC')
            );
            foreach ($rows as &$row) {
                $row['data'] = json_decode($row['data'], true);
            }
            return $rows;
        } catch (\Throwable $e) {
            $this->logger->warning('[StoreIntelligence] getDiagnostics(' . $category . ') failed: ' . $e->getMessage());
            return [];
        }
    }
}

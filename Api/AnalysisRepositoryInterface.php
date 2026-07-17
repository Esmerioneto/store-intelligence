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

namespace Egsn\StoreIntelligence\Api;

/**
 * @api
 */
interface AnalysisRepositoryInterface
{
    /**
     * Get list.
     *
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function getList(int $page = 1, int $pageSize = 20): array;

    /**
     * Última análise concluída.
     *
     * @return Egsn\StoreIntelligence\Api\Data\AnalysisInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getLatest(): \Egsn\StoreIntelligence\Api\Data\AnalysisInterface;

    /**
     * Get by id.
     *
     * @param int $id
     * @return Egsn\StoreIntelligence\Api\Data\AnalysisInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): \Egsn\StoreIntelligence\Api\Data\AnalysisInterface;

    /**
     * Get performance diagnostics.
     *
     * @return array
     */
    public function getPerformanceDiagnostics(): array;

    /**
     * Get errors diagnostics.
     *
     * @return array
     */
    public function getErrorsDiagnostics(): array;

    /**
     * Get sales diagnostics.
     *
     * @return array
     */
    public function getSalesDiagnostics(): array;

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
    ): array;

    /**
     * Get recommendation by id.
     *
     * @param int $id
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getRecommendationById(int $id): array;

    /**
     * Dismiss recommendation.
     *
     * @param int $id
     * @return bool
     */
    public function dismissRecommendation(int $id): bool;

    /**
     * Get collectors.
     *
     * @return array
     */
    public function getCollectors(): array;

    /**
     * Get collector result.
     *
     * @param string $code
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function getCollectorResult(string $code, int $page = 1, int $pageSize = 50): array;

    /**
     * Export analysis as CSV string
     *
     * @param int $id
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function exportAnalysis(int $id): string;

    /**
     * Collector results of a single analysis (data JSON decoded).
     *
     * @param int $analysisId
     * @return mixed[]
     */
    public function getAnalysisResults(int $analysisId): array;

    /**
     * Total AI tokens consumed in the current month.
     *
     * @return int
     */
    public function getMonthlyTokenUsage(): int;

    /**
     * Compare collector statuses of the two latest completed analyses.
     *
     * Returns ['new' => [], 'resolved' => [], 'persistent' => [], 'current_id' => ?int, 'previous_id' => ?int]
     * where each list maps collector code => current (or previous, for resolved) status.
     *
     * @return array
     */
    public function getComparison(): array;
}

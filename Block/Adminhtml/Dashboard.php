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

namespace Egsn\StoreIntelligence\Block\Adminhtml;

use Egsn\StoreIntelligence\Api\AnalysisRepositoryInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Dashboard extends Template
{
    /**
     * Constructor.
     *
     * @param Context $context
     * @param AnalysisRepositoryInterface $repository
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly AnalysisRepositoryInterface $repository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get latest analysis.
     *
     * @return array
     */
    public function getLatestAnalysis(): array
    {
        try {
            return $this->repository->getLatest()->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Get top recommendations.
     *
     * @param int $limit
     * @return array
     */
    public function getTopRecommendations(int $limit = 5): array
    {
        try {
            $result = $this->repository->getRecommendations('', '', 1, $limit);
            return $result['items'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Get score history.
     *
     * @param int $limit
     * @return array
     */
    public function getScoreHistory(int $limit = 8): array
    {
        try {
            $result = $this->repository->getList(1, $limit);
            return array_reverse($result['items'] ?? []);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Get run analysis url.
     *
     * @return string
     */
    public function getRunAnalysisUrl(): string
    {
        return $this->getUrl('egsn_si/analysis/run');
    }

    /**
     * Get status url.
     *
     * @return string
     */
    public function getStatusUrl(): string
    {
        return $this->getUrl('egsn_si/analysis/status');
    }

    /**
     * Score por categoria da última análise concluída (performance/errors/sales).
     *
     * @return array
     */
    public function getCategoryScores(): array
    {
        try {
            return [
                'performance' => $this->weightedScore($this->repository->getPerformanceDiagnostics()),
                'errors'      => $this->weightedScore($this->repository->getErrorsDiagnostics()),
                'sales'       => $this->weightedScore($this->repository->getSalesDiagnostics()),
            ];
        } catch (\Throwable) {
            return ['performance' => null, 'errors' => null, 'sales' => null];
        }
    }

    /**
     * Consumo de tokens de IA no mês corrente e orçamento configurado (0 = sem limite).
     *
     * @return array
     */
    public function getTokenUsage(): array
    {
        try {
            $used = $this->repository->getMonthlyTokenUsage();
        } catch (\Throwable) {
            $used = 0;
        }
        return [
            'used'   => $used,
            'budget' => (int) ($this->_scopeConfig->getValue('egsn_si/general/token_budget_monthly') ?: 0),
        ];
    }

    /**
     * Get comparison.
     *
     * @return array
     */
    public function getComparison(): array
    {
        try {
            return $this->repository->getComparison();
        } catch (\Throwable) {
            return ['new' => [], 'resolved' => [], 'persistent' => [], 'current_id' => null, 'previous_id' => null];
        }
    }

    /**
     * Score ponderado de uma categoria.
     *
     * Mesma fórmula de Orchestrator::computeScore, aplicada sobre linhas do banco.
     *
     * @param array $rows
     * @return int|null
     */
    private function weightedScore(array $rows): ?int
    {
        $statusScore  = ['ok' => 100, 'warning' => 50, 'critical' => 0];
        $statusWeight = ['ok' => 1, 'warning' => 2, 'critical' => 3];

        $weightedSum = 0;
        $totalWeight = 0;
        foreach ($rows as $row) {
            $status = $row['status'] ?? '';
            if (!isset($statusScore[$status])) {
                continue;
            }
            $score  = isset($row['score']) && $row['score'] !== null ? (int) $row['score'] : $statusScore[$status];
            $weight = $statusWeight[$status];

            $weightedSum += $score * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? (int) round($weightedSum / $totalWeight) : null;
    }
}

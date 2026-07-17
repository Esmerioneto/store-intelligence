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

use Egsn\StoreIntelligence\Collector\CollectorInterface;
use Egsn\StoreIntelligence\Model\AiProvider\ProviderPool;
use Egsn\StoreIntelligence\Model\Exception\AnalysisFailedException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

class Orchestrator
{
    /**
     * Constructor.
     *
     * @param AnalysisLock $lock
     * @param CollectorRunner $runner
     * @param PromptBuilder $promptBuilder
     * @param ProviderPool $providerPool
     * @param ResourceConnection $resource
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param AnalysisScope $analysisScope
     * @param CollectorPool $collectorPool
     */
    public function __construct(
        private readonly AnalysisLock         $lock,
        private readonly CollectorRunner      $runner,
        private readonly PromptBuilder        $promptBuilder,
        private readonly ProviderPool         $providerPool,
        private readonly ResourceConnection   $resource,
        private readonly DateTime             $dateTime,
        private readonly LoggerInterface      $logger,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly AnalysisScope        $analysisScope,
        private readonly CollectorPool        $collectorPool
    ) {
    }

    /**
     * Run.
     *
     * @param string $triggeredBy
     * @return int|null
     */
    public function run(string $triggeredBy): ?int
    {
        if (!$this->lock->acquire(uniqid('si_', true))) {
            return null;
        }

        $conn          = $this->resource->getConnection();
        $analysisTable = $this->resource->getTableName('egsn_si_analysis');
        $resultTable   = $this->resource->getTableName('egsn_si_collector_result');
        $recTable      = $this->resource->getTableName('egsn_si_recommendation');

        $websiteId = (int) ($this->scopeConfig->getValue('egsn_si/general/website_id') ?: 0);
        $this->analysisScope->setWebsiteId($websiteId ?: null);

        try {
            $conn->insert($analysisTable, [
                'status'       => 'running',
                'triggered_by' => $triggeredBy,
                'website_id'   => $this->analysisScope->getWebsiteId(),
                'ran_at'       => $this->dateTime->gmtDate('Y-m-d H:i:s'),
                'created_at'   => $this->dateTime->gmtDate('Y-m-d H:i:s'),
            ]);
            $analysisId = (int) $conn->lastInsertId();

            $results = [];
            foreach ($this->collectorPool->getAll() as $collector) {
                $result = $this->runner->run($collector);
                if ($result === null) {
                    $conn->insert($resultTable, [
                        'analysis_id' => $analysisId,
                        'collector'   => $collector->getCode(),
                        'category'    => $collector->getCategory(),
                        'status'      => 'timeout',
                        'data'        => json_encode([]),
                    ]);
                    continue;
                }

                $conn->insert($resultTable, [
                    'analysis_id' => $analysisId,
                    'collector'   => $result->getCollectorCode(),
                    'category'    => $result->getCategory(),
                    'status'      => $result->getStatus(),
                    'score'       => $result->getScore(),
                    'data'        => json_encode($result->toArray()),
                ]);

                $results[] = $result;
            }

            $score = $this->computeScore($results);

            $aiResponse = null;
            $summaryFallback = null;
            if (!empty($results) && $this->isTokenBudgetExceeded($analysisTable)) {
                $summaryFallback = 'Resumo de IA não gerado: orçamento mensal de tokens atingido.';
                $this->logger->warning('[StoreIntelligence] Monthly AI token budget reached; skipping AI analysis.');
            } elseif (!empty($results)) {
                $context = $this->getStoreContext();
                $context['Score de saúde calculado'] = $score . '/100';

                $system     = $this->promptBuilder->buildSystemPrompt();
                $user       = $this->promptBuilder->buildUserPrompt($results, $context);
                $aiResponse = $this->providerPool->analyze($system, $user);

                $conn->update($analysisTable, ['ai_prompt' => $system . "\n---\n" . $user], ['id = ?' => $analysisId]);

                foreach ($aiResponse->getRecommendations() as $rec) {
                    $conn->insert($recTable, [
                        'analysis_id'      => $analysisId,
                        'collector'        => $rec['collector'] ?? '',
                        'category'         => $rec['category'] ?? '',
                        'priority'         => $rec['priority'] ?? 'improvement',
                        'title'            => $rec['title'] ?? '',
                        'description'      => $rec['description'] ?? '',
                        'action'           => $rec['action'] ?? '',
                        'estimated_impact' => $rec['estimated_impact'] ?? null,
                    ]);

                    // Dispensa versões antigas da mesma recomendação (mesmo collector + título),
                    // para a grid não acumular duplicatas entre análises.
                    $conn->update($recTable, [
                        'dismissed'    => 1,
                        'dismissed_at' => $this->dateTime->gmtDate('Y-m-d H:i:s'),
                    ], [
                        'analysis_id < ?' => $analysisId,
                        'dismissed = 0',
                        'collector = ?'   => $rec['collector'] ?? '',
                        'title = ?'       => $rec['title'] ?? '',
                    ]);
                }
            }

            $conn->update($analysisTable, [
                'status'      => 'completed',
                'finished_at' => $this->dateTime->gmtDate('Y-m-d H:i:s'),
                'score'       => $score,
                'summary'     => $aiResponse?->getSummary() ?? $summaryFallback,
                'ai_provider' => $aiResponse?->getProvider(),
                'ai_tokens'   => $aiResponse?->getTokensUsed(),
            ], ['id = ?' => $analysisId]);

            return $analysisId;
        } catch (\Throwable $e) {
            $this->logger->error('StoreIntelligence analysis failed', ['exception' => $e->getMessage()]);
            if (isset($analysisId)) {
                $conn->update($analysisTable, [
                    'status'        => 'failed',
                    'error_message' => mb_strcut($e->getMessage(), 0, 65535),
                    'finished_at'   => $this->dateTime->gmtDate('Y-m-d H:i:s'),
                ], ['id = ?' => $analysisId]);
                throw new AnalysisFailedException($analysisId, $e);
            }
            throw $e;
        } finally {
            $this->lock->release();
        }
    }

    /**
     * Score determinístico da loja.
     *
     * Média dos scores dos collectors ponderada pela severidade do status, para
     * que problemas pesem mais que itens saudáveis. Substitui o score subjetivo
     * da IA, que variava entre execuções idênticas.
     *
     * @param array $results
     * @return int|null
     */
    public function computeScore(array $results): ?int
    {
        $statusScore  = ['ok' => 100, 'warning' => 50, 'critical' => 0];
        $statusWeight = ['ok' => 1, 'warning' => 2, 'critical' => 3];

        $weightedSum = 0;
        $totalWeight = 0;
        foreach ($results as $result) {
            $status = $result->getStatus();
            if (!isset($statusScore[$status])) {
                continue; // skipped/timeout não entram no cálculo
            }
            $score  = $result->getScore() ?? $statusScore[$status];
            $weight = $statusWeight[$status];

            $weightedSum += $score * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? (int) round($weightedSum / $totalWeight) : null;
    }

    /**
     * Verifica o orçamento mensal de tokens de IA.
     *
     * Bloqueia a chamada de IA quando a soma de ai_tokens do mês corrente
     * atinge o orçamento configurado (0 = sem limite).
     *
     * @param string $analysisTable
     * @return bool
     */
    private function isTokenBudgetExceeded(string $analysisTable): bool
    {
        $budget = (int) ($this->scopeConfig->getValue('egsn_si/general/token_budget_monthly') ?: 0);
        if ($budget <= 0) {
            return false;
        }

        $conn = $this->resource->getConnection();
        $used = (int) $conn->fetchOne(
            $conn->select()
                ->from($analysisTable, [new \Zend_Db_Expr('COALESCE(SUM(ai_tokens), 0)')])
                ->where('created_at >= ?', $this->dateTime->gmtDate('Y-m-01 00:00:00'))
        );

        return $used >= $budget;
    }

    /**
     * Get store context.
     *
     * @return array
     */
    private function getStoreContext(): array
    {
        return [
            'Data da análise' => $this->dateTime->gmtDate('Y-m-d H:i'),
            'Plataforma'      => 'Magento 2',
        ];
    }
}

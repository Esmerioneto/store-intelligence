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

class RecommendationView extends Template
{
    /**
     * @var array|null
     */
    private ?array $cachedRecommendation = null;

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
     * Get recommendation.
     *
     * @return array
     */
    public function getRecommendation(): array
    {
        if ($this->cachedRecommendation !== null) {
            return $this->cachedRecommendation;
        }
        $id = (int) $this->getRequest()->getParam('id');
        try {
            $this->cachedRecommendation = $this->repository->getRecommendationById($id);
        } catch (\Throwable) {
            $this->cachedRecommendation = [];
        }
        return $this->cachedRecommendation;
    }

    /**
     * Get collector items.
     *
     * @return array
     */
    public function getCollectorItems(): array
    {
        $rec = $this->getRecommendation();
        if (empty($rec['collector'])) {
            return [];
        }
        try {
            $result = $this->repository->getCollectorResult($rec['collector'], 1, 50);
            $data   = $result['items'][0]['data'] ?? [];
            return $data['items'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Get dismiss url.
     *
     * @return string
     */
    public function getDismissUrl(): string
    {
        return $this->getUrl('egsn_si/recommendations/dismiss');
    }

    /**
     * Get export url.
     *
     * @return string
     */
    public function getExportUrl(): string
    {
        $rec = $this->getRecommendation();
        return $this->getUrl('egsn_si/analysis/export', ['id' => $rec['analysis_id'] ?? 0]);
    }
}

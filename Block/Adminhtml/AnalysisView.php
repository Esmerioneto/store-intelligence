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
use Magento\Store\Model\StoreManagerInterface;

class AnalysisView extends Template
{
    /**
     * Constructor.
     *
     * @param Context $context
     * @param AnalysisRepositoryInterface $repository
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly AnalysisRepositoryInterface $repository,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get analysis id.
     *
     * @return int
     */
    public function getAnalysisId(): int
    {
        return (int) $this->getRequest()->getParam('id');
    }

    /**
     * Dados da análise (com recomendações); [] quando não encontrada.
     *
     * @return array
     */
    public function getAnalysis(): array
    {
        try {
            return $this->repository->getById($this->getAnalysisId())->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Resultados dos collectors da análise.
     *
     * @return array
     */
    public function getResults(): array
    {
        try {
            return $this->repository->getAnalysisResults($this->getAnalysisId());
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Nome do website do escopo; null/0 = global.
     *
     * @param int|null $websiteId
     * @return string
     */
    public function getScopeLabel(?int $websiteId): string
    {
        if (!$websiteId) {
            return (string) __('Global');
        }
        try {
            return (string) $this->storeManager->getWebsite($websiteId)->getName();
        } catch (\Throwable) {
            return (string) __('Website #%1', $websiteId);
        }
    }
}

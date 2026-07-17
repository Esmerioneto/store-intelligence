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

class History extends Template
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
     * Nome do website do escopo da análise; null/0 = global.
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

    /**
     * Get analyses.
     *
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function getAnalyses(int $page = 1, int $pageSize = 20): array
    {
        try {
            return $this->repository->getList($page, $pageSize);
        } catch (\Throwable) {
            return ['items' => [], 'total' => 0];
        }
    }
}

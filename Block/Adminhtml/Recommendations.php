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

class Recommendations extends Template
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
     * Get recommendations.
     *
     * @param string $priority
     * @param string $category
     * @param int $page
     * @return array
     */
    public function getRecommendations(string $priority = '', string $category = '', int $page = 1): array
    {
        try {
            return $this->repository->getRecommendations($priority, $category, $page, 20);
        } catch (\Throwable) {
            return ['items' => [], 'total' => 0];
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
}

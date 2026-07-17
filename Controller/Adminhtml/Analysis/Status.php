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

namespace Egsn\StoreIntelligence\Controller\Adminhtml\Analysis;

use Egsn\StoreIntelligence\Api\AnalysisRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

class Status extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Egsn_StoreIntelligence::dashboard';

    /**
     * Constructor.
     *
     * @param Context $context
     * @param AnalysisRepositoryInterface $repository
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        private readonly AnalysisRepositoryInterface $repository,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Execute.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();
        $latest = $this->repository->getList(1, 1)['items'][0] ?? [];

        return $result->setData([
            'id'     => isset($latest['id']) ? (int) $latest['id'] : null,
            'status' => $latest['status'] ?? null,
            'score'  => isset($latest['score']) ? (int) $latest['score'] : null,
        ]);
    }
}

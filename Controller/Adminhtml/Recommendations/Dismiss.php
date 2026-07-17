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

namespace Egsn\StoreIntelligence\Controller\Adminhtml\Recommendations;

use Egsn\StoreIntelligence\Api\AnalysisRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

class Dismiss extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Egsn_StoreIntelligence::recommendations';

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
        private readonly JsonFactory                 $jsonFactory
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
        $id     = (int) $this->getRequest()->getParam('id');
        $ok     = $this->repository->dismissRecommendation($id);
        return $result->setData(['success' => $ok]);
    }
}

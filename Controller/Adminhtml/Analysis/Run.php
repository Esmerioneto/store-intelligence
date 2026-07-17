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

use Egsn\StoreIntelligence\MessageQueue\Publisher;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

class Run extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Egsn_StoreIntelligence::dashboard';

    /**
     * Constructor.
     *
     * @param Context $context
     * @param Publisher $publisher
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        private readonly Publisher $publisher,
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
        try {
            $this->publisher->publish('manual');
            return $result->setData(['success' => true, 'message' => __('Analysis scheduled successfully')]);
        } catch (\Throwable $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}

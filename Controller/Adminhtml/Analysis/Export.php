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

use Egsn\StoreIntelligence\Model\Export\CsvExporter;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

class Export extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Egsn_StoreIntelligence::history';

    /**
     * Constructor.
     *
     * @param Context $context
     * @param CsvExporter $exporter
     * @param FileFactory $fileFactory
     * @param DateTime $dateTime
     */
    public function __construct(
        Context $context,
        private readonly CsvExporter $exporter,
        private readonly FileFactory $fileFactory,
        private readonly DateTime    $dateTime
    ) {
        parent::__construct($context);
    }

    /**
     * Execute.
     *
     * @return ResultInterface|Magento\Framework\App\ResponseInterface
     */
    public function execute(): ResultInterface|\Magento\Framework\App\ResponseInterface
    {
        $id       = (int) $this->getRequest()->getParam('id');
        $filename = sprintf('store-intelligence-%s.csv', $this->dateTime->gmtDate('Y-m-d'));
        $content  = $this->exporter->export($id);

        return $this->fileFactory->create(
            $filename,
            $content,
            DirectoryList::VAR_DIR,
            'text/csv'
        );
    }
}

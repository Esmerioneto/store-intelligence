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

namespace Egsn\StoreIntelligence\Model\Api;

use Egsn\StoreIntelligence\Api\RunAnalysisInterface;
use Egsn\StoreIntelligence\MessageQueue\Publisher;

class RunAnalysis implements RunAnalysisInterface
{
    /**
     * Constructor.
     *
     * @param Publisher $publisher
     */
    public function __construct(private readonly Publisher $publisher)
    {
    }
    /**
     * Trigger a new store intelligence analysis via API
     *
     * @return array
     */
    public function execute(): array
    {
        $this->publisher->publish('api');
        return ['status' => 'queued', 'message' => 'Analysis queued successfully'];
    }
}

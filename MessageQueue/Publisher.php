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

namespace Egsn\StoreIntelligence\MessageQueue;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Store\Model\StoreManagerInterface;

class Publisher
{
    private const TOPIC = 'egsn.store.intelligence.analysis.run';

    /**
     * "Each website separately" option of the Analysis Scope config.
     */
    public const SCOPE_EACH_WEBSITE = -1;

    /**
     * Constructor.
     *
     * @param PublisherInterface $publisher
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly PublisherInterface    $publisher,
        private readonly ScopeConfigInterface  $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Publish one analysis message — or one per website when the scope is "each website separately".
     *
     * @param string $triggeredBy
     * @return void
     */
    public function publish(string $triggeredBy): void
    {
        $scope = (int) ($this->scopeConfig->getValue('egsn_si/general/website_id') ?: 0);

        if ($scope !== self::SCOPE_EACH_WEBSITE) {
            $this->publisher->publish(self::TOPIC, json_encode(['triggered_by' => $triggeredBy]));
            return;
        }

        foreach ($this->storeManager->getWebsites() as $website) {
            $this->publisher->publish(self::TOPIC, json_encode([
                'triggered_by' => $triggeredBy,
                'website_id'   => (int) $website->getId(),
            ]));
        }
    }
}

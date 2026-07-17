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

use Magento\Framework\MessageQueue\PublisherInterface;

class Publisher
{
    private const TOPIC = 'egsn.store.intelligence.analysis.run';

    /**
     * Constructor.
     *
     * @param PublisherInterface $publisher
     */
    public function __construct(private readonly PublisherInterface $publisher)
    {
    }

    /**
     * Publish.
     *
     * @param string $triggeredBy
     * @return void
     */
    public function publish(string $triggeredBy): void
    {
        $this->publisher->publish(self::TOPIC, json_encode(['triggered_by' => $triggeredBy]));
    }
}

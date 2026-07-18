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

namespace Egsn\StoreIntelligence\Cron;

use Egsn\StoreIntelligence\MessageQueue\Publisher;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class RunAnalysis
{
    /**
     * Constructor.
     *
     * @param Publisher $publisher
     * @param ScopeConfigInterface $scopeConfig
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly Publisher            $publisher,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly TimezoneInterface    $timezone
    ) {
    }

    /**
     * Execute.
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->scopeConfig->getValue('egsn_si/schedule/enabled')) {
            return;
        }

        if (!$this->isWithinExecutionWindow()) {
            return;
        }

        $this->publisher->publish('cron');
    }

    /**
     * Is within execution window.
     *
     * @return bool
     */
    private function isWithinExecutionWindow(): bool
    {
        $start = $this->scopeConfig->getValue('egsn_si/schedule/window_start') ?: '01:00';
        $end   = $this->scopeConfig->getValue('egsn_si/schedule/window_end') ?: '05:00';
        // Store timezone: Magento matches cron expressions in the store timezone,
        // so the window must be compared in the same timezone the admin configured.
        $current = $this->timezone->date()->format('H:i');

        // Supports windows that cross midnight (e.g. 23:00-02:00)
        if ($start <= $end) {
            return $current >= $start && $current <= $end;
        }
        return $current >= $start || $current <= $end;
    }
}

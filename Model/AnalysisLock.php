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

namespace Egsn\StoreIntelligence\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

class AnalysisLock
{
    private const TABLE     = 'egsn_si_lock';
    private const CONFIG    = 'egsn_si/thresholds/global_timeout_minutes';
    private const LOCK_NAME = 'egsn_si_analysis';

    /**
     * Constructor.
     *
     * @param ResourceConnection $resource
     * @param ScopeConfigInterface $scopeConfig
     * @param DateTime $dateTime
     * @param LockManagerInterface $lockManager
     */
    public function __construct(
        private readonly ResourceConnection  $resource,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly DateTime            $dateTime,
        private readonly LockManagerInterface $lockManager
    ) {
    }

    /**
     * Acquire.
     *
     * @param string $processId
     * @return bool
     */
    public function acquire(string $processId): bool
    {
        if (!$this->lockManager->lock(self::LOCK_NAME, 0)) {
            return false;
        }

        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $conn->delete($table);

        $minutes = (int) $this->scopeConfig->getValue(self::CONFIG) ?: 10;
        $now     = $this->dateTime->gmtDate('Y-m-d H:i:s');
        $expires = $this->dateTime->gmtDate('Y-m-d H:i:s', $this->dateTime->gmtTimestamp() + ($minutes * 60));

        $conn->insert($table, [
            'locked_at'  => $now,
            'expires_at' => $expires,
            'process_id' => $processId,
        ]);

        return true;
    }

    /**
     * Release.
     *
     * @return void
     */
    public function release(): void
    {
        $this->lockManager->unlock(self::LOCK_NAME);
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $conn->delete($table);
    }
}

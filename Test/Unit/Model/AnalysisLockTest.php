<?php
/**
 * Esmerio Neto
 *
 * @category Egsn
 * @package Egsn_StoreIntelligence
 *
 * @copyright Copyright (c) 2026 Esmerio Neto.
 *
 * @author Esmerio Neto <esmerioneto@gmail.com>
 */
declare(strict_types=1);

namespace Egsn\StoreIntelligence\Test\Unit\Model;

use Egsn\StoreIntelligence\Model\AnalysisLock;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\TestCase;

class AnalysisLockTest extends TestCase
{
    /**
     * Test acquire returns true when no lock exists.
     *
     * @return void
     */
    public function testAcquireReturnsTrueWhenNoLockExists(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())->method('insert');

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn(10);

        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturn('2026-01-01 00:00:00');
        $dateTime->method('gmtTimestamp')->willReturn(time());

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);

        $lock = new AnalysisLock($resource, $config, $dateTime, $lockManager);
        $this->assertTrue($lock->acquire('test-pid'));
    }

    /**
     * Test acquire returns false when lock exists.
     *
     * @return void
     */
    public function testAcquireReturnsFalseWhenLockExists(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        $config = $this->createMock(ScopeConfigInterface::class);

        $dateTime = $this->createMock(DateTime::class);

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(false);

        $lock = new AnalysisLock($resource, $config, $dateTime, $lockManager);
        $this->assertFalse($lock->acquire('test-pid'));
    }

    /**
     * Test release deletes all locks.
     *
     * @return void
     */
    public function testReleaseDeletesAllLocks(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())->method('delete');

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        $config = $this->createMock(ScopeConfigInterface::class);

        $dateTime = $this->createMock(DateTime::class);

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->once())->method('unlock');

        $lock = new AnalysisLock($resource, $config, $dateTime, $lockManager);
        $lock->release();
    }
}

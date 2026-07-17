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

namespace Egsn\StoreIntelligence\Test\Unit\Cron;

use Egsn\StoreIntelligence\Cron\GarbageCollection;
use Egsn\StoreIntelligence\Model\WebhookNotifier;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GarbageCollectionTest extends TestCase
{
    /**
     * Make adapter.
     *
     * @param array $stuckIds
     * @return Mysql
     */
    private function makeAdapter(array $stuckIds): Mysql
    {
        $select = $this->getMockBuilder(Select::class)->disableOriginalConstructor()->getMock();
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $adapter = $this->getMockBuilder(Mysql::class)->disableOriginalConstructor()->getMock();
        $adapter->method('select')->willReturn($select);
        $adapter->method('fetchCol')->willReturn($stuckIds);
        return $adapter;
    }

    /**
     * Test watchdog fails stuck analyses even with cleanup disabled.
     *
     * @return void
     */
    public function testWatchdogFailsStuckAnalysesEvenWithCleanupDisabled(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(static fn (string $path) => match ($path) {
            'egsn_si/thresholds/global_timeout_minutes' => '10',
            'egsn_si/cleanup/enabled'                   => null,
            default                                     => null,
        });

        $updates = [];
        $adapter = $this->makeAdapter(['7', '8']);
        $adapter->method('update')->willReturnCallback(
            static function (string $table, array $data, array $where) use (&$updates): int {
                $updates[] = [$table, $data, $where];
                return 2;
            }
        );
        $adapter->expects($this->never())->method('delete');

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        // cada análise travada gera uma notificação de falha via webhook
        $webhook = $this->createMock(WebhookNotifier::class);
        $webhook->expects($this->exactly(2))->method('send');

        (new GarbageCollection($scopeConfig, $resource, $logger, $webhook))->execute();

        $this->assertCount(1, $updates);
        $this->assertSame('egsn_si_analysis', $updates[0][0]);
        $this->assertSame('failed', $updates[0][1]['status']);
        $this->assertStringContainsString('10 min', $updates[0][1]['error_message']);
        $this->assertSame([7, 8], $updates[0][2]['id IN (?)']);
    }

    /**
     * Test cleanup deletes old analyses when enabled.
     *
     * @return void
     */
    public function testCleanupDeletesOldAnalysesWhenEnabled(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(static fn (string $path) => match ($path) {
            'egsn_si/cleanup/enabled'                   => '1',
            'egsn_si/cleanup/retention_days'            => '30',
            'egsn_si/thresholds/global_timeout_minutes' => '10',
            default                                     => null,
        });

        $adapter = $this->makeAdapter([]);
        $adapter->expects($this->never())->method('update');
        $deletes = [];
        $adapter->method('delete')->willReturnCallback(
            static function (string $table, $where = '') use (&$deletes): int {
                $deletes[] = [$table, $where];
                return 5;
            }
        );

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        $webhook = $this->createMock(WebhookNotifier::class);
        $webhook->expects($this->never())->method('send');

        (new GarbageCollection(
            $scopeConfig,
            $resource,
            $this->createMock(LoggerInterface::class),
            $webhook
        ))->execute();

        $this->assertCount(2, $deletes);
        $this->assertSame('egsn_si_analysis', $deletes[0][0]);
        $this->assertSame(['created_at < DATE_SUB(NOW(), INTERVAL ? DAY)' => 30], $deletes[0][1]);
        $this->assertSame('egsn_si_lock', $deletes[1][0]);
    }
}

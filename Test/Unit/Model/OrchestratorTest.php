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

use Egsn\StoreIntelligence\Collector\CollectionResult;
use Egsn\StoreIntelligence\Collector\CollectorInterface;
use Egsn\StoreIntelligence\Model\AiProvider\AnalysisResponse;
use Egsn\StoreIntelligence\Model\AiProvider\ProviderPool;
use Egsn\StoreIntelligence\Model\AnalysisLock;
use Egsn\StoreIntelligence\Model\AnalysisScope;
use Egsn\StoreIntelligence\Model\CollectorPool;
use Egsn\StoreIntelligence\Model\CollectorRunner;
use Egsn\StoreIntelligence\Model\Orchestrator;
use Egsn\StoreIntelligence\Model\PromptBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OrchestratorTest extends TestCase
{
    /**
     * Test run returns null when lock not acquired.
     *
     * @return void
     */
    public function testRunReturnsNullWhenLockNotAcquired(): void
    {
        $lock = $this->createMock(AnalysisLock::class);
        $lock->method('acquire')->willReturn(false);

        $orchestrator = new Orchestrator(
            lock: $lock,
            runner: $this->createMock(CollectorRunner::class),
            promptBuilder: $this->createMock(PromptBuilder::class),
            providerPool: $this->createMock(ProviderPool::class),
            resource: $this->createMock(ResourceConnection::class),
            dateTime: $this->createMock(DateTime::class),
            logger: $this->createMock(LoggerInterface::class),
            scopeConfig: $this->createMock(ScopeConfigInterface::class),
            analysisScope: $this->createMock(AnalysisScope::class),
            collectorPool: new CollectorPool([])
        );

        $this->assertNull($orchestrator->run('manual'));
    }

    /**
     * Test compute score weighs severity and ignores timeouts.
     *
     * @return void
     */
    public function testComputeScoreWeighsSeverityAndIgnoresTimeouts(): void
    {
        $orchestrator = new Orchestrator(
            lock: $this->createMock(AnalysisLock::class),
            runner: $this->createMock(CollectorRunner::class),
            promptBuilder: $this->createMock(PromptBuilder::class),
            providerPool: $this->createMock(ProviderPool::class),
            resource: $this->createMock(ResourceConnection::class),
            dateTime: $this->createMock(DateTime::class),
            logger: $this->createMock(LoggerInterface::class),
            scopeConfig: $this->createMock(ScopeConfigInterface::class),
            analysisScope: $this->createMock(AnalysisScope::class),
            collectorPool: new CollectorPool([])
        );

        $results = [
            new CollectionResult('a', 'sales', 'ok', [], [], 100),      // peso 1
            new CollectionResult('b', 'sales', 'ok', [], [], null),     // sem score -> 100, peso 1
            new CollectionResult('c', 'errors', 'warning', [], [], 40), // peso 2
            new CollectionResult('d', 'performance', 'critical', [], [], 0), // peso 3
            new CollectionResult('e', 'performance', 'timeout', [], [], null), // ignorado
        ];

        // (100*1 + 100*1 + 40*2 + 0*3) / (1+1+2+3) = 280/7 = 40
        $this->assertSame(40, $orchestrator->computeScore($results));
        $this->assertNull($orchestrator->computeScore([]));

        // determinístico: mesma entrada, mesmo score
        $this->assertSame($orchestrator->computeScore($results), $orchestrator->computeScore($results));
    }

    /**
     * Test run saves analysis and returns id.
     *
     * @return void
     */
    public function testRunSavesAnalysisAndReturnsId(): void
    {
        $lock = $this->createMock(AnalysisLock::class);
        $lock->method('acquire')->willReturn(true);

        $collector = $this->createMock(CollectorInterface::class);
        $collector->method('getCode')->willReturn('test');
        $collector->method('getCategory')->willReturn('sales');

        $collectionResult = new CollectionResult('test', 'sales', 'ok', ['count' => 0]);

        $runner = $this->createMock(CollectorRunner::class);
        $runner->method('run')->willReturn($collectionResult);

        $promptBuilder = $this->createMock(PromptBuilder::class);
        $promptBuilder->method('buildSystemPrompt')->willReturn('system');
        $promptBuilder->method('buildUserPrompt')->willReturn('user');

        $aiResponse = new AnalysisResponse(75, 'Loja saudável', [
            [
                'collector'   => 'test',
                'category'    => 'sales',
                'priority'    => 'warning',
                'title'       => 'Minificação e Merge de JS/CSS',
                'description' => 'desc',
                'action'      => 'act',
            ],
        ], 100, 'claude');
        $providerPool = $this->createMock(ProviderPool::class);
        $providerPool->method('analyze')->willReturn($aiResponse);

        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturn('2026-01-01 00:00:00');

        $adapter = $this->getMockBuilder(\Magento\Framework\DB\Adapter\Pdo\Mysql::class)
            ->disableOriginalConstructor()
            ->getMock();
        $adapter->method('lastInsertId')->willReturn('42');
        $updates = [];
        $adapter->method('update')->willReturnCallback(
            static function (string $table, array $data, array $where) use (&$updates): int {
                $updates[] = [$table, $data, $where];
                return 1;
            }
        );

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        $orchestrator = new Orchestrator(
            lock: $lock,
            runner: $runner,
            promptBuilder: $promptBuilder,
            providerPool: $providerPool,
            resource: $resource,
            dateTime: $dateTime,
            logger: $this->createMock(LoggerInterface::class),
            scopeConfig: $this->createMock(ScopeConfigInterface::class),
            analysisScope: $this->createMock(AnalysisScope::class),
            collectorPool: new CollectorPool([$collector])
        );

        $id = $orchestrator->run('manual');
        $this->assertSame(42, $id);

        // nova recomendação dispensa versões antigas do mesmo collector+título
        $dedupe = array_values(array_filter(
            $updates,
            static fn (array $u): bool => $u[0] === 'egsn_si_recommendation'
        ));
        $this->assertCount(1, $dedupe);
        $this->assertSame(1, $dedupe[0][1]['dismissed']);
        $this->assertSame(42, $dedupe[0][2]['analysis_id < ?']);
        $this->assertSame('Minificação e Merge de JS/CSS', $dedupe[0][2]['title = ?']);
    }

    /**
     * Test run propagates exception from provider.
     *
     * @return void
     */
    public function testRunPropagatesExceptionFromProvider(): void
    {
        $lock = $this->createMock(AnalysisLock::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $collectionResult = new CollectionResult('test', 'sales', 'ok', []);
        $collector        = $this->createMock(CollectorInterface::class);
        $collector->method('getCode')->willReturn('test');
        $collector->method('getCategory')->willReturn('sales');

        $runner = $this->createMock(CollectorRunner::class);
        $runner->method('run')->willReturn($collectionResult);

        $promptBuilder = $this->createMock(PromptBuilder::class);
        $promptBuilder->method('buildSystemPrompt')->willReturn('system');
        $promptBuilder->method('buildUserPrompt')->willReturn('user');

        $providerPool = $this->createMock(ProviderPool::class);
        $providerPool->method('analyze')->willThrowException(new \RuntimeException('API down'));

        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturn('2026-01-01 00:00:00');

        $adapter = $this->getMockBuilder(\Magento\Framework\DB\Adapter\Pdo\Mysql::class)
            ->disableOriginalConstructor()
            ->getMock();
        $adapter->method('lastInsertId')->willReturn('1');
        $updates = [];
        $adapter->method('update')->willReturnCallback(
            static function (string $table, array $data, array $where) use (&$updates): int {
                $updates[] = [$table, $data, $where];
                return 1;
            }
        );

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        $orchestrator = new Orchestrator(
            lock: $lock,
            runner: $runner,
            promptBuilder: $promptBuilder,
            providerPool: $providerPool,
            resource: $resource,
            dateTime: $dateTime,
            logger: $this->createMock(LoggerInterface::class),
            scopeConfig: $this->createMock(ScopeConfigInterface::class),
            analysisScope: $this->createMock(AnalysisScope::class),
            collectorPool: new CollectorPool([$collector])
        );

        try {
            $orchestrator->run('manual');
            $this->fail('RuntimeException esperada');
        } catch (\RuntimeException $e) {
            $this->assertSame('API down', $e->getMessage());
        }

        // a falha grava status + motivo na linha da análise
        $failed = end($updates);
        $this->assertSame('egsn_si_analysis', $failed[0]);
        $this->assertSame('failed', $failed[1]['status']);
        $this->assertSame('API down', $failed[1]['error_message']);
    }
}

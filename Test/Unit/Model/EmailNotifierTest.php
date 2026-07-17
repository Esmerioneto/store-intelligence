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

use Egsn\StoreIntelligence\Model\EmailNotifier;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EmailNotifierTest extends TestCase
{
    /**
     * Make notifier.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param TransportBuilder $transportBuilder
     * @param ResourceConnection|null $resource
     * @return EmailNotifier
     */
    private function makeNotifier(
        ScopeConfigInterface $scopeConfig,
        TransportBuilder $transportBuilder,
        ?ResourceConnection $resource = null
    ): EmailNotifier {
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturn('2026-01-01 00:00:00');

        return new EmailNotifier(
            $transportBuilder,
            $scopeConfig,
            $resource ?? $this->createMock(ResourceConnection::class),
            $dateTime,
            new Escaper(),
            $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * Test send does nothing when disabled.
     *
     * @return void
     */
    public function testSendDoesNothingWhenDisabled(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(null);

        $transportBuilder = $this->createMock(TransportBuilder::class);
        $transportBuilder->expects($this->never())->method('setTemplateIdentifier');

        $this->makeNotifier($scopeConfig, $transportBuilder)->send(1);
    }

    /**
     * Test send delivers to each recipient and logs.
     *
     * @return void
     */
    public function testSendDeliversToEachRecipientAndLogs(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(static fn (string $path) => match ($path) {
            'egsn_si/email/enabled'    => '1',
            'egsn_si/email/recipients' => "a@test.com\nb@test.com",
            default                    => null,
        });

        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('fetchRow')->willReturn(['id' => 7, 'score' => 60, 'summary' => 'resumo']);
        $adapter->method('fetchAll')->willReturn([]);
        $inserts = [];
        $adapter->method('insert')->willReturnCallback(
            static function (string $table, array $data) use (&$inserts): int {
                $inserts[] = $data;
                return 1;
            }
        );

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->exactly(2))->method('sendMessage');

        $transportBuilder = $this->createMock(TransportBuilder::class);
        foreach (['setTemplateIdentifier', 'setTemplateOptions', 'setTemplateVars', 'setFromByScope', 'addTo'] as $m) {
            $transportBuilder->method($m)->willReturnSelf();
        }
        $transportBuilder->method('getTransport')->willReturn($transport);

        $this->makeNotifier($scopeConfig, $transportBuilder, $resource)->send(7);

        $this->assertCount(2, $inserts);
        $this->assertSame('sent', $inserts[0]['status']);
        $this->assertSame('a@test.com', $inserts[0]['recipient']);
        $this->assertSame('b@test.com', $inserts[1]['recipient']);
    }
}

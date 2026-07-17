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

use Egsn\StoreIntelligence\Model\WebhookNotifier;
use GuzzleHttp\Client;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class WebhookNotifierTest extends TestCase
{
    /**
     * Test send does nothing when disabled.
     *
     * @return void
     */
    public function testSendDoesNothingWhenDisabled(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(null);

        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('post');

        $notifier = new WebhookNotifier(
            $client,
            $scopeConfig,
            $this->createMock(ResourceConnection::class),
            $this->createMock(LoggerInterface::class)
        );
        $notifier->send(1);
    }

    /**
     * Test send posts text payload.
     *
     * @return void
     */
    public function testSendPostsTextPayload(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(static fn (string $path) => match ($path) {
            'egsn_si/webhook/enabled' => '1',
            'egsn_si/webhook/url'     => 'https://hooks.example.com/x',
            default                   => null,
        });

        $select = $this->getMockBuilder(\Magento\Framework\DB\Select::class)
            ->disableOriginalConstructor()
            ->getMock();
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $adapter = $this->getMockBuilder(Mysql::class)->disableOriginalConstructor()->getMock();
        $adapter->method('select')->willReturn($select);
        $adapter->method('fetchRow')->willReturn(['id' => 9, 'score' => 55, 'summary' => 'resumo da análise']);
        $adapter->method('fetchOne')->willReturn(3);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $captured = null;
        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('post')->willReturnCallback(
            static function (string $url, array $options) use (&$captured, $response): ResponseInterface {
                $captured = ['url' => $url, 'options' => $options];
                return $response;
            }
        );

        $notifier = new WebhookNotifier(
            $client,
            $scopeConfig,
            $resource,
            $this->createMock(LoggerInterface::class)
        );
        $notifier->send(9);

        $this->assertSame('https://hooks.example.com/x', $captured['url']);
        $payload = json_decode($captured['options']['body'], true);
        $this->assertStringContainsString('análise #9', $payload['text']);
        $this->assertStringContainsString('55/100', $payload['text']);
        $this->assertStringContainsString('3 recomendação(ões) crítica(s)', $payload['text']);
        $this->assertStringContainsString('resumo da análise', $payload['text']);
    }
}

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

namespace Egsn\StoreIntelligence\Test\Unit\MessageQueue;

use Egsn\StoreIntelligence\MessageQueue\Publisher;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class PublisherTest extends TestCase
{
    /**
     * Make subject capturing published messages.
     *
     * @param string|null $scope
     * @param array $websiteIds
     * @param array $published
     * @return Publisher
     */
    private function makeSubject(?string $scope, array $websiteIds, array &$published): Publisher
    {
        $queuePublisher = $this->createMock(PublisherInterface::class);
        $queuePublisher->method('publish')->willReturnCallback(
            static function (string $topic, $message) use (&$published) {
                $published[] = json_decode($message, true);
                return null;
            }
        );

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn($scope);

        $websites = [];
        foreach ($websiteIds as $id) {
            $website = $this->createMock(WebsiteInterface::class);
            $website->method('getId')->willReturn($id);
            $websites[] = $website;
        }
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getWebsites')->willReturn($websites);

        return new Publisher($queuePublisher, $scopeConfig, $storeManager);
    }

    /**
     * Test global scope publishes a single message without website id.
     *
     * @return void
     */
    public function testGlobalScopePublishesSingleMessage(): void
    {
        $published = [];
        $this->makeSubject('0', [1, 2], $published)->publish('cron');

        $this->assertSame([['triggered_by' => 'cron']], $published);
    }

    /**
     * Test specific website scope publishes a single message (orchestrator reads config).
     *
     * @return void
     */
    public function testSpecificWebsiteScopePublishesSingleMessage(): void
    {
        $published = [];
        $this->makeSubject('2', [1, 2], $published)->publish('manual');

        $this->assertSame([['triggered_by' => 'manual']], $published);
    }

    /**
     * Test "each website separately" publishes one message per website.
     *
     * @return void
     */
    public function testEachWebsiteScopePublishesOneMessagePerWebsite(): void
    {
        $published = [];
        $this->makeSubject('-1', [1, 2, 3], $published)->publish('cron');

        $this->assertSame([
            ['triggered_by' => 'cron', 'website_id' => 1],
            ['triggered_by' => 'cron', 'website_id' => 2],
            ['triggered_by' => 'cron', 'website_id' => 3],
        ], $published);
    }
}

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

namespace Egsn\StoreIntelligence\Test\Unit\Collector\Performance;

use Egsn\StoreIntelligence\Collector\Performance\OpcacheCollector;
use PHPUnit\Framework\TestCase;

class OpcacheCollectorTest extends TestCase
{
    /**
     * Test get code and category.
     *
     * @return void
     */
    public function testGetCodeAndCategory(): void
    {
        $collector = new OpcacheCollector();
        $this->assertSame('opcache', $collector->getCode());
        $this->assertSame('performance', $collector->getCategory());
    }

    /**
     * Test collect returns result with enabled key.
     *
     * @return void
     */
    public function testCollectReturnsResultWithEnabledKey(): void
    {
        $collector = new OpcacheCollector();
        $result    = $collector->collect();

        $this->assertSame('opcache', $result->getCollectorCode());
        $this->assertSame('performance', $result->getCategory());
        $this->assertArrayHasKey('enabled', $result->getSummary());
        $this->assertContains($result->getStatus(), ['ok', 'warning', 'critical']);
    }
}

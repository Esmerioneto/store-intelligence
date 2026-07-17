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

namespace Egsn\StoreIntelligence\Test\Unit\Model\AiProvider;

use Egsn\StoreIntelligence\Api\AiProviderInterface;
use Egsn\StoreIntelligence\Model\AiProvider\AnalysisResponse;
use Egsn\StoreIntelligence\Model\AiProvider\ProviderPool;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ProviderPoolTest extends TestCase
{
    /**
     * Provider.
     *
     * @param string $code
     * @param bool $configured
     * @return AiProviderInterface
     */
    private function provider(string $code, bool $configured): AiProviderInterface
    {
        $provider = $this->createMock(AiProviderInterface::class);
        $provider->method('getCode')->willReturn($code);
        $provider->method('isConfigured')->willReturn($configured);
        return $provider;
    }

    /**
     * Test analyze uses only the selected provider.
     *
     * @return void
     */
    public function testAnalyzeUsesOnlySelectedProvider(): void
    {
        $response = new AnalysisResponse(80, 'ok', [], 10, 'gemini');

        $claude = $this->provider('claude', true);
        $claude->expects($this->never())->method('analyze');

        $gemini = $this->provider('gemini', true);
        $gemini->expects($this->once())->method('analyze')->willReturn($response);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('gemini');

        $pool = new ProviderPool(
            $scopeConfig,
            ['claude' => $claude, 'gemini' => $gemini]
        );

        $this->assertSame($response, $pool->analyze('system', 'user'));
    }

    /**
     * Test analyze propagates selected provider error without fallback.
     *
     * @return void
     */
    public function testAnalyzePropagatesErrorWithoutFallback(): void
    {
        $gemini = $this->provider('gemini', true);
        $gemini->method('analyze')->willThrowException(new \RuntimeException('gemini down'));

        $claude = $this->provider('claude', true);
        $claude->expects($this->never())->method('analyze');

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('gemini');

        $pool = new ProviderPool(
            $scopeConfig,
            ['gemini' => $gemini, 'claude' => $claude]
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('gemini down');
        $pool->analyze('system', 'user');
    }

    /**
     * Test getConfigured throws when selected provider has no API key.
     *
     * @return void
     */
    public function testGetConfiguredThrowsWhenSelectedProviderNotConfigured(): void
    {
        $gemini = $this->provider('gemini', false);
        $claude = $this->provider('claude', true);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('gemini');

        $pool = new ProviderPool(
            $scopeConfig,
            ['gemini' => $gemini, 'claude' => $claude]
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("AI provider 'gemini' is not configured");
        $pool->getConfigured();
    }
}

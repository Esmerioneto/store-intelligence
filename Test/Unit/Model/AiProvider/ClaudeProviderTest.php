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

use Egsn\StoreIntelligence\Model\AiProvider\ClaudeProvider;
use GuzzleHttp\Client;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class ClaudeProviderTest extends TestCase
{
    /**
     * Make encryptor.
     *
     * @return EncryptorInterface
     */
    private function makeEncryptor(): EncryptorInterface
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturnArgument(0);

        return $encryptor;
    }

    /**
     * Make client.
     *
     * @param int $status
     * @param string $body
     * @return Client
     */
    private function makeClient(int $status, string $body): Client
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn($body);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn($stream);

        $client = $this->createMock(Client::class);
        $client->method('post')->willReturn($response);

        return $client;
    }

    /**
     * Test get code.
     *
     * @return void
     */
    public function testGetCode(): void
    {
        $client = $this->createMock(Client::class);
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn('test-key');

        $provider = new ClaudeProvider($client, $config, $this->makeEncryptor());
        $this->assertSame('claude', $provider->getCode());
    }

    /**
     * Test is configured returns false with no key.
     *
     * @return void
     */
    public function testIsConfiguredReturnsFalseWithNoKey(): void
    {
        $client = $this->createMock(Client::class);
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn(null);

        $provider = new ClaudeProvider($client, $config, $this->makeEncryptor());
        $this->assertFalse($provider->isConfigured());
    }

    /**
     * Test is configured returns true with key.
     *
     * @return void
     */
    public function testIsConfiguredReturnsTrueWithKey(): void
    {
        $client = $this->createMock(Client::class);
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn('sk-ant-test123');

        $provider = new ClaudeProvider($client, $config, $this->makeEncryptor());
        $this->assertTrue($provider->isConfigured());
    }

    /**
     * Test analyze returns analysis response.
     *
     * @return void
     */
    public function testAnalyzeReturnsAnalysisResponse(): void
    {
        $responseBody = json_encode([
            'content' => [['text' => json_encode([
                'score'           => 85,
                'summary'         => 'Loja em bom estado',
                'recommendations' => [],
            ])]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ]);

        $client = $this->makeClient(200, $responseBody);

        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn('sk-ant-test123');

        $provider = new ClaudeProvider($client, $config, $this->makeEncryptor());
        $response = $provider->analyze('system prompt', 'user prompt');

        $this->assertSame(85, $response->getScore());
        $this->assertSame('Loja em bom estado', $response->getSummary());
        $this->assertSame(150, $response->getTokensUsed());
        $this->assertSame('claude', $response->getProvider());
        $this->assertSame([], $response->getRecommendations());
    }

    /**
     * Test analyze throws on api error.
     *
     * @return void
     */
    public function testAnalyzeThrowsOnApiError(): void
    {
        $client = $this->makeClient(401, '{"type":"error","error":{"type":"authentication_error"}}');

        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn('bad-key');

        $provider = new ClaudeProvider($client, $config, $this->makeEncryptor());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Claude API returned HTTP 401');
        $provider->analyze('system prompt', 'user prompt');
    }
}

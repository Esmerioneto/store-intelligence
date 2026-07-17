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

namespace Egsn\StoreIntelligence\Model\AiProvider;

use Egsn\StoreIntelligence\Api\AiProviderInterface;
use Egsn\StoreIntelligence\Api\Data\AnalysisResponseInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class ClaudeProvider implements AiProviderInterface
{
    private const API_URL      = 'https://api.anthropic.com/v1/messages';
    private const API_VER      = '2023-06-01';
    private const CONFIG_KEY   = 'egsn_si/general/ai_api_key_claude';
    private const CONFIG_MODEL = 'egsn_si/general/ai_model_claude';

    /**
     * Constructor.
     *
     * @param Client $client
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly Client             $client,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return 'claude';
    }

    /**
     * Is configured.
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->scopeConfig->getValue(self::CONFIG_KEY));
    }

    /**
     * Analyze.
     *
     * @param string $systemPrompt
     * @param string $userPrompt
     * @return AnalysisResponseInterface
     * @throws \RuntimeException
     * @throws GuzzleException
     */
    public function analyze(string $systemPrompt, string $userPrompt): AnalysisResponseInterface
    {
        $apiKey = $this->encryptor->decrypt($this->scopeConfig->getValue(self::CONFIG_KEY));
        $model  = $this->scopeConfig->getValue(self::CONFIG_MODEL) ?: 'claude-sonnet-4-6';

        $payload = json_encode([
            'model'       => $model,
            'max_tokens'  => 4096,
            'temperature' => 0,
            'system'     => $systemPrompt,
            'messages'   => [['role' => 'user', 'content' => $userPrompt]],
        ]);

        $response = $this->client->post(self::API_URL, [
            'headers' => [
                'x-api-key'         => $apiKey,
                'anthropic-version' => self::API_VER,
                'content-type'      => 'application/json',
            ],
            'body'        => $payload,
            'http_errors' => false,
        ]);

        $statusCode = $response->getStatusCode();
        $body       = $response->getBody()->getContents();

        if ($statusCode >= 400) {
            throw new \RuntimeException(
                sprintf('Claude API returned HTTP %d: %s', $statusCode, $body)
            );
        }

        $data = json_decode($body, true);
        $text   = $data['content'][0]['text'] ?? '{}';
        $tokens = ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0);

        $parsed = json_decode($text, true) ?? [];

        return new AnalysisResponse(
            score:           (int) ($parsed['score'] ?? 50),
            summary:         (string) ($parsed['summary'] ?? ''),
            recommendations: (array) ($parsed['recommendations'] ?? []),
            tokensUsed:      (int) $tokens,
            provider:        $this->getCode()
        );
    }
}

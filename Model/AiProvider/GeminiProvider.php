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

class GeminiProvider implements AiProviderInterface
{
    private const API_URL      = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
    private const CONFIG_KEY   = 'egsn_si/general/ai_api_key_gemini';
    private const CONFIG_MODEL = 'egsn_si/general/ai_model_gemini';

    /**
     * Constructor.
     *
     * @param Client $client
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly Client               $client,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface   $encryptor
    ) {
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return 'gemini';
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
        $model  = $this->scopeConfig->getValue(self::CONFIG_MODEL) ?: 'gemini-2.0-flash';

        $url = sprintf(self::API_URL, $model) . '?key=' . urlencode($apiKey);

        $payload = json_encode([
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature'      => 0,
            ],
        ]);

        $response = $this->client->post($url, [
            'headers'     => ['content-type' => 'application/json'],
            'body'        => $payload,
            'http_errors' => false,
        ]);

        $statusCode = $response->getStatusCode();
        $body       = $response->getBody()->getContents();

        if ($statusCode >= 400) {
            throw new \RuntimeException(
                sprintf('Gemini API returned HTTP %d: %s', $statusCode, $body)
            );
        }

        $data   = json_decode($body, true);
        $text   = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        $tokens = $data['usageMetadata']['totalTokenCount'] ?? 0;

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

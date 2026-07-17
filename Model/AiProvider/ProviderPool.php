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
use Magento\Framework\App\Config\ScopeConfigInterface;

class ProviderPool
{
    private const CONFIG_PROVIDER = 'egsn_si/general/ai_provider';

    /**
     * Constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param array $providers
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly array $providers = []
    ) {
    }

    /**
     * Analisa somente com o provider selecionado na configuração, sem fallback.
     *
     * @param string $systemPrompt
     * @param string $userPrompt
     * @return AnalysisResponseInterface
     */
    public function analyze(string $systemPrompt, string $userPrompt): AnalysisResponseInterface
    {
        return $this->getConfigured()->analyze($systemPrompt, $userPrompt);
    }

    /**
     * Get configured.
     *
     * @return AiProviderInterface
     * @throws \RuntimeException if the selected provider is missing or has no API key
     */
    public function getConfigured(): AiProviderInterface
    {
        $code = (string) ($this->scopeConfig->getValue(self::CONFIG_PROVIDER) ?: 'claude');
        $provider = $this->providers[$code]
            ?? throw new \RuntimeException("AI provider '{$code}' is not registered.");
        if (!$provider->isConfigured()) {
            throw new \RuntimeException("AI provider '{$code}' is not configured (missing API key).");
        }
        return $provider;
    }
}

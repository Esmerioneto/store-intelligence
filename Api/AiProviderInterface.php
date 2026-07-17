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

namespace Egsn\StoreIntelligence\Api;

use Egsn\StoreIntelligence\Api\Data\AnalysisResponseInterface;

/**
 * @api
 */
interface AiProviderInterface
{
    /**
     * Analyze.
     *
     * @param string $systemPrompt
     * @param string $userPrompt
     * @return AnalysisResponseInterface
     */
    public function analyze(string $systemPrompt, string $userPrompt): AnalysisResponseInterface;
    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string;
    /**
     * Is configured.
     *
     * @return bool
     */
    public function isConfigured(): bool;
}

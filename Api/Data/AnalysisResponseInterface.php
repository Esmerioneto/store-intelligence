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

namespace Egsn\StoreIntelligence\Api\Data;

/**
 * @api
 */
interface AnalysisResponseInterface
{
    /**
     * Get score.
     *
     * @return int
     */
    public function getScore(): int;
    /**
     * Get summary.
     *
     * @return string
     */
    public function getSummary(): string;
    /**
     * Get recommendations.
     *
     * @return array
     */
    public function getRecommendations(): array;
    /**
     * Get tokens used.
     *
     * @return int
     */
    public function getTokensUsed(): int;
    /**
     * Get provider.
     *
     * @return string
     */
    public function getProvider(): string;
}

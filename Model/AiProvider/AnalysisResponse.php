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

use Egsn\StoreIntelligence\Api\Data\AnalysisResponseInterface;

class AnalysisResponse implements AnalysisResponseInterface
{
    /**
     * Constructor.
     *
     * @param int $score
     * @param string $summary
     * @param array $recommendations
     * @param int $tokensUsed
     * @param string $provider
     */
    public function __construct(
        private readonly int    $score,
        private readonly string $summary,
        private readonly array  $recommendations,
        private readonly int    $tokensUsed,
        private readonly string $provider
    ) {
    }

    /**
     * Get score.
     *
     * @return int
     */
    public function getScore(): int
    {
        return $this->score;
    }

    /**
     * Get summary.
     *
     * @return string
     */
    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * Get recommendations.
     *
     * @return array
     */
    public function getRecommendations(): array
    {
        return $this->recommendations;
    }

    /**
     * Get tokens used.
     *
     * @return int
     */
    public function getTokensUsed(): int
    {
        return $this->tokensUsed;
    }

    /**
     * Get provider.
     *
     * @return string
     */
    public function getProvider(): string
    {
        return $this->provider;
    }
}

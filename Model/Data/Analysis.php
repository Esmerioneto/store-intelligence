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

namespace Egsn\StoreIntelligence\Model\Data;

use Egsn\StoreIntelligence\Api\Data\AnalysisInterface;
use Egsn\StoreIntelligence\Api\Data\RecommendationInterface;

class Analysis implements AnalysisInterface
{
    /**
     * Constructor.
     *
     * @param array $data linha da tabela egsn_si_analysis
     * @param array $recommendations
     */
    public function __construct(
        private readonly array $data,
        private readonly array $recommendations = []
    ) {
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId(): int
    {
        return (int) ($this->data['id'] ?? 0);
    }

    /**
     * Get status.
     *
     * @return string
     */
    public function getStatus(): string
    {
        return (string) ($this->data['status'] ?? '');
    }

    /**
     * Get triggered by.
     *
     * @return string
     */
    public function getTriggeredBy(): string
    {
        return (string) ($this->data['triggered_by'] ?? '');
    }

    /**
     * Get website id.
     *
     * @return int|null
     */
    public function getWebsiteId(): ?int
    {
        return isset($this->data['website_id']) ? (int) $this->data['website_id'] : null;
    }

    /**
     * Get ran at.
     *
     * @return string|null
     */
    public function getRanAt(): ?string
    {
        return isset($this->data['ran_at']) ? (string) $this->data['ran_at'] : null;
    }

    /**
     * Get finished at.
     *
     * @return string|null
     */
    public function getFinishedAt(): ?string
    {
        return isset($this->data['finished_at']) ? (string) $this->data['finished_at'] : null;
    }

    /**
     * Get score.
     *
     * @return int|null
     */
    public function getScore(): ?int
    {
        return isset($this->data['score']) ? (int) $this->data['score'] : null;
    }

    /**
     * Get summary.
     *
     * @return string|null
     */
    public function getSummary(): ?string
    {
        return isset($this->data['summary']) ? (string) $this->data['summary'] : null;
    }

    /**
     * Get error message.
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return isset($this->data['error_message']) ? (string) $this->data['error_message'] : null;
    }

    /**
     * Get ai provider.
     *
     * @return string|null
     */
    public function getAiProvider(): ?string
    {
        return isset($this->data['ai_provider']) ? (string) $this->data['ai_provider'] : null;
    }

    /**
     * Get ai tokens.
     *
     * @return int|null
     */
    public function getAiTokens(): ?int
    {
        return isset($this->data['ai_tokens']) ? (int) $this->data['ai_tokens'] : null;
    }

    /**
     * Get created at.
     *
     * @return string
     */
    public function getCreatedAt(): string
    {
        return (string) ($this->data['created_at'] ?? '');
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
     * To array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $data = $this->data;
        if ($this->recommendations) {
            $data['recommendations'] = array_map(
                static fn (RecommendationInterface $rec): array => $rec->toArray(),
                $this->recommendations
            );
        }
        return $data;
    }
}

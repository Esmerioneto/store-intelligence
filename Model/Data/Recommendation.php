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

use Egsn\StoreIntelligence\Api\Data\RecommendationInterface;

class Recommendation implements RecommendationInterface
{
    /**
     * Constructor.
     *
     * @param array $data linha da tabela egsn_si_recommendation
     */
    public function __construct(private readonly array $data)
    {
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
     * Get analysis id.
     *
     * @return int
     */
    public function getAnalysisId(): int
    {
        return (int) ($this->data['analysis_id'] ?? 0);
    }

    /**
     * Get collector.
     *
     * @return string
     */
    public function getCollector(): string
    {
        return (string) ($this->data['collector'] ?? '');
    }

    /**
     * Get category.
     *
     * @return string
     */
    public function getCategory(): string
    {
        return (string) ($this->data['category'] ?? '');
    }

    /**
     * Get priority.
     *
     * @return string
     */
    public function getPriority(): string
    {
        return (string) ($this->data['priority'] ?? '');
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return (string) ($this->data['title'] ?? '');
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return (string) ($this->data['description'] ?? '');
    }

    /**
     * Get action.
     *
     * @return string
     */
    public function getAction(): string
    {
        return (string) ($this->data['action'] ?? '');
    }

    /**
     * Get estimated impact.
     *
     * @return string|null
     */
    public function getEstimatedImpact(): ?string
    {
        return isset($this->data['estimated_impact']) ? (string) $this->data['estimated_impact'] : null;
    }

    /**
     * Get dismissed.
     *
     * @return bool
     */
    public function getDismissed(): bool
    {
        return (bool) ($this->data['dismissed'] ?? false);
    }

    /**
     * Get dismissed at.
     *
     * @return string|null
     */
    public function getDismissedAt(): ?string
    {
        return isset($this->data['dismissed_at']) ? (string) $this->data['dismissed_at'] : null;
    }

    /**
     * To array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->data;
    }
}

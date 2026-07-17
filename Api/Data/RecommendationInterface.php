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
 * Contrato de dados de uma recomendação (REST/GraphQL).
 * @api
 */
interface RecommendationInterface
{
    /**
     * Get id.
     *
     * @return int
     */
    public function getId(): int;

    /**
     * Get analysis id.
     *
     * @return int
     */
    public function getAnalysisId(): int;

    /**
     * Get collector.
     *
     * @return string
     */
    public function getCollector(): string;

    /**
     * Get category.
     *
     * @return string
     */
    public function getCategory(): string;

    /**
     * Get priority.
     *
     * @return string
     */
    public function getPriority(): string;

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Get action.
     *
     * @return string
     */
    public function getAction(): string;

    /**
     * Get estimated impact.
     *
     * @return string|null
     */
    public function getEstimatedImpact(): ?string;

    /**
     * Get dismissed.
     *
     * @return bool
     */
    public function getDismissed(): bool;

    /**
     * Get dismissed at.
     *
     * @return string|null
     */
    public function getDismissedAt(): ?string;

    /**
     * Representação em array (uso interno: blocks/resolvers).
     *
     * @return array
     */
    public function toArray(): array;
}

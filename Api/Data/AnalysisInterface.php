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
 * Contrato de dados de uma análise (REST/GraphQL).
 * @api
 */
interface AnalysisInterface
{
    /**
     * Get id.
     *
     * @return int
     */
    public function getId(): int;

    /**
     * Get status.
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Get triggered by.
     *
     * @return string
     */
    public function getTriggeredBy(): string;

    /**
     * Get website id.
     *
     * @return int|null
     */
    public function getWebsiteId(): ?int;

    /**
     * Get ran at.
     *
     * @return string|null
     */
    public function getRanAt(): ?string;

    /**
     * Get finished at.
     *
     * @return string|null
     */
    public function getFinishedAt(): ?string;

    /**
     * Get score.
     *
     * @return int|null
     */
    public function getScore(): ?int;

    /**
     * Get summary.
     *
     * @return string|null
     */
    public function getSummary(): ?string;

    /**
     * Get error message.
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string;

    /**
     * Get ai provider.
     *
     * @return string|null
     */
    public function getAiProvider(): ?string;

    /**
     * Get ai tokens.
     *
     * @return int|null
     */
    public function getAiTokens(): ?int;

    /**
     * Get created at.
     *
     * @return string
     */
    public function getCreatedAt(): string;

    /**
     * Get recommendations.
     *
     * @return array
     */
    public function getRecommendations(): array;

    /**
     * Representação em array (uso interno: blocks/resolvers).
     *
     * @return array
     */
    public function toArray(): array;
}

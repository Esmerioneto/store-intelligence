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

namespace Egsn\StoreIntelligence\Collector;

class CollectionResult
{
    /**
     * Constructor.
     *
     * @param string $collectorCode
     * @param string $category
     * @param string $status
     * @param array $summary
     * @param array $items
     * @param int|null $score
     */
    public function __construct(
        private readonly string $collectorCode,
        private readonly string $category,
        private readonly string $status,
        private readonly array  $summary,
        private readonly array  $items = [],
        private readonly ?int   $score = null
    ) {
    }

    /**
     * Get collector code.
     *
     * @return string
     */
    public function getCollectorCode(): string
    {
        return $this->collectorCode;
    }

    /**
     * Get category.
     *
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * Get status.
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Get summary.
     *
     * @return array
     */
    public function getSummary(): array
    {
        return $this->summary;
    }

    /**
     * Get items.
     *
     * @return array
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Get score.
     *
     * @return int|null
     */
    public function getScore(): ?int
    {
        return $this->score;
    }

    /**
     * To array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'items'   => $this->items,
        ];
    }
}

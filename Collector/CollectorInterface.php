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

interface CollectorInterface
{
    /**
     * Collect.
     *
     * @return CollectionResult
     */
    public function collect(): CollectionResult;
    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string;
    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string;
    /**
     * Get category.
     *
     * @return string
     */
    public function getCategory(): string;
}

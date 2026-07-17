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

namespace Egsn\StoreIntelligence\Model;

use Egsn\StoreIntelligence\Collector\CollectorInterface;

/**
 * Lista única de collectors registrados via DI, compartilhada entre
 * Orchestrator e o comando de self-check.
 */
class CollectorPool
{
    /**
     * Constructor.
     *
     * @param CollectorInterface[] $collectors
     */
    public function __construct(private readonly array $collectors = [])
    {
    }

    /**
     * Get all.
     *
     * @return CollectorInterface[]
     */
    public function getAll(): array
    {
        return $this->collectors;
    }
}

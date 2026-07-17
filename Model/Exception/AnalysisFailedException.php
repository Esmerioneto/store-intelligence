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

namespace Egsn\StoreIntelligence\Model\Exception;

/**
 * Falha de análise carregando o id da linha marcada como failed,
 * para o Consumer conseguir notificar o motivo.
 */
class AnalysisFailedException extends \RuntimeException
{
    /**
     * Constructor.
     *
     * @param int $analysisId
     * @param \Throwable $previous
     */
    public function __construct(private readonly int $analysisId, \Throwable $previous)
    {
        parent::__construct($previous->getMessage(), 0, $previous);
    }

    /**
     * Get analysis id.
     *
     * @return int
     */
    public function getAnalysisId(): int
    {
        return $this->analysisId;
    }
}

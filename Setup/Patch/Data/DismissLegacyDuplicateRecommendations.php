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

namespace Egsn\StoreIntelligence\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Limpeza única: dispensa recomendações ativas que possuem uma versão mais
 * recente ativa do mesmo collector (duplicatas legadas com títulos divergentes,
 * geradas antes do temperature: 0 e da deduplicação automática).
 */
class DismissLegacyDuplicateRecommendations implements DataPatchInterface
{
    /**
     * Constructor.
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(private readonly ModuleDataSetupInterface $moduleDataSetup)
    {
    }

    /**
     * Apply.
     *
     * @return $this
     */
    public function apply(): self
    {
        $conn  = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('egsn_si_recommendation');

        $conn->query(
            // phpcs:ignore Magento2.SQL.RawQuery.FoundRawSql -- UPDATE com self-join não suportado pelo builder
            "UPDATE {$table} r
             JOIN {$table} newer
               ON newer.collector = r.collector
              AND newer.dismissed = 0
              AND newer.analysis_id > r.analysis_id
              SET r.dismissed = 1, r.dismissed_at = UTC_TIMESTAMP()
            WHERE r.dismissed = 0"
        );

        return $this;
    }

    /**
     * Get dependencies.
     *
     * @return array
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Get aliases.
     *
     * @return array
     */
    public function getAliases(): array
    {
        return [];
    }
}

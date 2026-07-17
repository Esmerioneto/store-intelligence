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

namespace Egsn\StoreIntelligence\Model\Resolver;

/**
 * Converte valores minúsculos do banco para os nomes SCREAMING_SNAKE_CASE
 * dos enums do schema GraphQL (e o inverso para argumentos de entrada).
 */
class EnumFormat
{
    /**
     * Formata uma linha de análise para saída GraphQL.
     *
     * @param array $row
     * @return array
     */
    public function analysis(array $row): array
    {
        if (isset($row['status'])) {
            $row['status'] = strtoupper((string) $row['status']);
        }
        if (!empty($row['recommendations']) && is_array($row['recommendations'])) {
            $row['recommendations'] = array_map([$this, 'recommendation'], $row['recommendations']);
        }
        return $row;
    }

    /**
     * Formata uma linha de recomendação para saída GraphQL.
     *
     * @param array $row
     * @return array
     */
    public function recommendation(array $row): array
    {
        foreach (['priority', 'category'] as $field) {
            if (isset($row[$field])) {
                $row[$field] = strtoupper((string) $row[$field]);
            }
        }
        return $row;
    }

    /**
     * Formata uma linha de collector para saída GraphQL.
     *
     * @param array $row
     * @return array
     */
    public function collector(array $row): array
    {
        if (isset($row['category'])) {
            $row['category'] = strtoupper((string) $row['category']);
        }
        return $row;
    }
}

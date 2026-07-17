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

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Escopo da análise em execução (null = global, todos os websites).
 * O Orchestrator define o escopo antes de rodar os collectors; collectors
 * com dados por loja consultam os helpers de filtro SQL.
 */
class AnalysisScope
{
    /**
     * @var int|null
     */
    private ?int $websiteId = null;

    /**
     * Constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection    $resource
    ) {
    }

    /**
     * Set website id.
     *
     * @param int|null $websiteId
     * @return void
     */
    public function setWebsiteId(?int $websiteId): void
    {
        $this->websiteId = $websiteId ?: null;
    }

    /**
     * Get website id.
     *
     * @return int|null
     */
    public function getWebsiteId(): ?int
    {
        return $this->websiteId;
    }

    /**
     * Get store ids.
     *
     * @return array
     */
    public function getStoreIds(): array
    {
        if ($this->websiteId === null) {
            return [];
        }
        try {
            return array_map('intval', $this->storeManager->getWebsite($this->websiteId)->getStoreIds());
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Fragmento " AND <coluna> IN (1,2)" para SQL raw; string vazia quando global.
     *
     * Seguro contra injection: apenas inteiros são interpolados.
     *
     * @param string $column
     * @return string
     */
    public function storeFilterSql(string $column): string
    {
        $ids = $this->getStoreIds();
        return $ids ? sprintf(' AND %s IN (%s)', $column, implode(',', $ids)) : '';
    }

    /**
     * Fragmento " AND <coluna> = <websiteId>" (para tabelas com website_id direto).
     *
     * @param string $column
     * @return string
     */
    public function websiteFilterSql(string $column): string
    {
        return $this->websiteId !== null ? sprintf(' AND %s = %d', $column, $this->websiteId) : '';
    }

    /**
     * Restringe produtos aos atribuídos ao website em escopo
     *
     * (evita falso-positivo com produtos exclusivos de outro website).
     *
     * @param string $productIdColumn
     * @return string
     */
    public function productWebsiteFilterSql(string $productIdColumn): string
    {
        if ($this->websiteId === null) {
            return '';
        }
        $table = $this->resource->getTableName('catalog_product_website');
        return sprintf(
            ' AND EXISTS (SELECT 1 FROM %s _cpw WHERE _cpw.product_id = %s AND _cpw.website_id = %d)',
            $table,
            $productIdColumn,
            $this->websiteId
        );
    }
}

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

// phpcs:disable Magento2.SQL.RawQuery.FoundRawSql -- SQL raw deliberado: só inteiros e nomes de tabela interpolados

namespace Egsn\StoreIntelligence\Collector\Errors;

use Egsn\StoreIntelligence\Collector\CollectionResult;
use Egsn\StoreIntelligence\Collector\CollectorInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;

class Missing404ImagesCollector implements CollectorInterface
{
    /**
     * Constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param Filesystem $filesystem
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Filesystem $filesystem,
        private readonly MetadataPool $metadataPool
    ) {
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return 'missing_images_404';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Imagens de Produto Faltando';
    }

    /**
     * Get category.
     *
     * @return string
     */
    public function getCategory(): string
    {
        return 'errors';
    }

    /**
     * Collect.
     *
     * @return CollectionResult
     */
    public function collect(): CollectionResult
    {
        $connection = $this->resourceConnection->getConnection();
            $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();

        $productTable  = $this->resourceConnection->getTableName('catalog_product_entity');
        $varcharTable  = $this->resourceConnection->getTableName('catalog_product_entity_varchar');
        $attributeTable = $this->resourceConnection->getTableName('eav_attribute');

        $sql = "SELECT DISTINCT p.entity_id AS product_id, p.sku, img.value AS image_path
                FROM {$productTable} p
                JOIN {$varcharTable} img ON img.{$linkField} = p.{$linkField}
                JOIN {$attributeTable} attr ON attr.attribute_id = img.attribute_id
                WHERE attr.attribute_code = 'image'
                  AND attr.entity_type_id = 4
                  AND img.value IS NOT NULL
                  AND img.value != 'no_selection'
                  AND img.store_id = 0
                LIMIT 500";

        try {
            $rows     = $connection->fetchAll($sql);
            $mediaDir = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);

            $missingItems = [];
            foreach ($rows as $row) {
                $imagePath = $row['image_path'];
                $filePath  = 'catalog/product' . $imagePath;

                if (!$mediaDir->isExist($filePath)) {
                    $missingItems[] = [
                        'product_id'     => (int) $row['product_id'],
                        'sku'            => $row['sku'],
                        'image_path'     => $imagePath,
                    ];
                }
            }
        } catch (\Throwable $e) {
            return new CollectionResult(
                $this->getCode(),
                $this->getCategory(),
                'ok',
                ['count' => 0, 'note' => 'Could not check images: ' . $e->getMessage()]
            );
        }

        $count  = count($missingItems);
        $status = $count === 0 ? 'ok' : ($count < 10 ? 'warning' : 'critical');

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: ['count' => $count],
            items: $missingItems,
            score: max(0, 100 - min(100, $count * 2))
        );
    }
}

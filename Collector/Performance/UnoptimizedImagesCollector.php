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

namespace Egsn\StoreIntelligence\Collector\Performance;

use Egsn\StoreIntelligence\Collector\CollectionResult;
use Egsn\StoreIntelligence\Collector\CollectorInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

class UnoptimizedImagesCollector implements CollectorInterface
{
    private const CONFIG_THRESHOLD  = 'egsn_si/thresholds/large_image_kb';
    private const DEFAULT_THRESHOLD = 200;
    private const MAX_ITEMS         = 100;
    private const IMAGE_EXTENSIONS  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const CATALOG_PATH      = 'catalog/product';

    /**
     * Constructor.
     *
     * @param Filesystem $filesystem
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return 'unoptimized_images';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Unoptimized Images';
    }

    /**
     * Get category.
     *
     * @return string
     */
    public function getCategory(): string
    {
        return 'performance';
    }

    /**
     * Collect.
     *
     * @return CollectionResult
     */
    public function collect(): CollectionResult
    {
        $thresholdKb = (int) ($this->scopeConfig->getValue(self::CONFIG_THRESHOLD) ?? self::DEFAULT_THRESHOLD);
        $thresholdB  = $thresholdKb * 1024;

        $count       = 0;
        $totalBytes  = 0;
        $items       = [];
        $status      = 'ok';

        try {
            $mediaDir = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            $basePath = $mediaDir->getAbsolutePath(self::CATALOG_PATH);

            if ($mediaDir->isDirectory(self::CATALOG_PATH)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($iterator as $file) {
                    if (!$file->isFile()) {
                        continue;
                    }

                    $ext = strtolower($file->getExtension());
                    if (!in_array($ext, self::IMAGE_EXTENSIONS, true)) {
                        continue;
                    }

                    $size = $file->getSize();
                    if ($size < $thresholdB) {
                        continue;
                    }

                    $count++;
                    $totalBytes += $size;

                    if (count($items) < self::MAX_ITEMS) {
                        $items[] = [
                            'path'    => ltrim(str_replace($basePath, '', $file->getPathname()), '/'),
                            'size_kb' => (int) round($size / 1024),
                            'type'    => $ext,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // Directory not accessible — return empty result
            $count = 0;
        }

        if ($count >= 50) {
            $status = 'critical';
        } elseif ($count > 0) {
            $status = 'warning';
        }

        $score = $count === 0 ? 100 : max(0, 100 - $count);

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: [
                'count'          => $count,
                'threshold_kb'   => $thresholdKb,
                'total_size_mb'  => round($totalBytes / 1048576, 2),
            ],
            items: $items,
            score: $score
        );
    }
}

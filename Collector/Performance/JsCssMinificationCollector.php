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

class JsCssMinificationCollector implements CollectorInterface
{
    /**
     * Constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
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
        return 'js_css_minification';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'JS/CSS Minification';
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
        $jsMinify  = $this->scopeConfig->isSetFlag('dev/js/minify_files');
        $cssMinify = $this->scopeConfig->isSetFlag('dev/css/minify_files');
        $jsMerge   = $this->scopeConfig->isSetFlag('dev/js/merge_files');
        $cssMerge  = $this->scopeConfig->isSetFlag('dev/css/merge_files');

        $settings = [$jsMinify, $cssMinify, $jsMerge, $cssMerge];
        $enabled  = array_filter($settings);
        $score    = (int) round((count($enabled) / count($settings)) * 100);

        if ($score === 100) {
            $status = 'ok';
        } elseif ($score === 0) {
            $status = 'critical';
        } else {
            $status = 'warning';
        }

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: [
                'js_minify'  => $jsMinify,
                'css_minify' => $cssMinify,
                'js_merge'   => $jsMerge,
                'css_merge'  => $cssMerge,
            ],
            score: $score
        );
    }
}

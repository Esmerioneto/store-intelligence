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

namespace Egsn\StoreIntelligence\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\StoreManagerInterface;

class Website implements OptionSourceInterface
{
    /**
     * Constructor.
     *
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(private readonly StoreManagerInterface $storeManager)
    {
    }

    /**
     * To option array.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [['value' => 0, 'label' => __('All websites (global)')]];
        foreach ($this->storeManager->getWebsites() as $website) {
            $options[] = ['value' => (int) $website->getId(), 'label' => $website->getName()];
        }
        return $options;
    }
}

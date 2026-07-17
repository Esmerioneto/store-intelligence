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

namespace Egsn\StoreIntelligence\Model\ResourceModel;

class Recommendation extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * _construct.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('egsn_si_recommendation', 'id');
    }
}

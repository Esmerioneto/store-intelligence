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

class AiProvider implements OptionSourceInterface
{
    /**
     * To option array.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'claude',  'label' => 'Claude (Anthropic)'],
            ['value' => 'openai',  'label' => 'OpenAI'],
            ['value' => 'gemini',  'label' => 'Gemini (Google AI Studio)'],
        ];
    }
}

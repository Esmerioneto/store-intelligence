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

namespace Egsn\StoreIntelligence\Model\Config\Backend;

use Magento\Cron\Model\Config\Source\Frequency;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Backend model shared by the "frequency" and "cron_expr" schedule fields.
 *
 * A non-empty custom cron expression (full crontab syntax: * , - / steps and
 * @shortcuts) overrides the Frequency/Start Time composition; when empty the
 * expression is composed from Frequency + Start Time. The result is stored at
 * the crontab config path consumed by crontab.xml.
 */
class CronConfig extends Value
{
    public const CRON_STRING_PATH = 'crontab/default/jobs/egsn_store_intelligence_run/schedule/cron_expr';

    private const SHORTCUTS = [
        '@yearly'   => '0 0 1 1 *',
        '@annually' => '0 0 1 1 *',
        '@monthly'  => '0 0 1 * *',
        '@weekly'   => '0 0 * * 0',
        '@daily'    => '0 0 * * *',
        '@hourly'   => '0 * * * *',
    ];

    /**
     * One crontab field: *, number or name, optional range, optional step, comma-separated list.
     */
    private const PART_PATTERN
        = '/^(\*|\d+(-\d+)?|[a-z]{3}(-[a-z]{3})?)(\/\d+)?(,(\*|\d+(-\d+)?|[a-z]{3}(-[a-z]{3})?)(\/\d+)?)*$/i';

    /**
     * Constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param WriterInterface $configWriter
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly WriterInterface $configWriter,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Validate the custom cron expression when saving it.
     *
     * @return $this
     * @throws ValidatorException
     */
    public function beforeSave(): self
    {
        $value = trim((string) $this->getValue());
        if ($this->isField('cron_expr') && $value !== '') {
            $this->setValue($value);
            $this->translate($value);
        }
        parent::beforeSave();

        return $this;
    }

    /**
     * Store the effective cron expression: custom expression or Frequency + Start Time.
     *
     * @return $this
     * @throws ValidatorException
     */
    public function afterSave(): self
    {
        $custom = trim($this->getFieldValue('cron_expr'));
        $this->configWriter->save(
            self::CRON_STRING_PATH,
            $custom !== '' ? $this->translate($custom) : $this->composeFromFrequency()
        );
        parent::afterSave();

        return $this;
    }

    /**
     * Translate @shortcuts and validate full crontab syntax.
     *
     * @param string $expr
     * @return string
     * @throws ValidatorException
     */
    private function translate(string $expr): string
    {
        $lower = strtolower($expr);
        if ($lower === '@reboot') {
            throw new ValidatorException(
                __('@reboot is not supported: Magento cron has no boot event. Use a periodic expression.')
            );
        }
        if (str_starts_with($lower, '@')) {
            if (!isset(self::SHORTCUTS[$lower])) {
                throw new ValidatorException(__('Unknown cron shortcut "%1".', $expr));
            }
            return self::SHORTCUTS[$lower];
        }

        $parts = preg_split('/\s+/', $expr, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) < 5 || count($parts) > 6) {
            throw new ValidatorException(
                __('Invalid cron expression "%1": expected 5 or 6 space-separated fields.', $expr)
            );
        }
        foreach ($parts as $part) {
            if (!preg_match(self::PART_PATTERN, $part)) {
                throw new ValidatorException(__('Invalid cron expression field "%1".', $part));
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Compose the expression from the Frequency and Start Time fields.
     *
     * @return string
     */
    private function composeFromFrequency(): string
    {
        $time = $this->getData('groups/schedule/fields/time/value')
            ?: explode(',', (string) $this->_config->getValue('egsn_si/schedule/time') ?: '01,00,00');
        $frequency = $this->getFieldValue('frequency');

        $cronExprArray = [
            (int) ($time[1] ?? 0),                              // Minute
            (int) ($time[0] ?? 0),                              // Hour
            $frequency === Frequency::CRON_MONTHLY ? '1' : '*', // Day of the Month
            '*',                                                // Month of the Year
            $frequency === Frequency::CRON_WEEKLY ? '1' : '*',  // Day of the Week
        ];

        return implode(' ', $cronExprArray);
    }

    /**
     * Is this instance saving the given schedule field.
     *
     * @param string $field
     * @return bool
     */
    private function isField(string $field): bool
    {
        return str_ends_with((string) $this->getPath(), '/' . $field);
    }

    /**
     * Get a schedule field value: own value, posted form value, or stored config.
     *
     * @param string $field
     * @return string
     */
    private function getFieldValue(string $field): string
    {
        if ($this->isField($field)) {
            return (string) $this->getValue();
        }

        $posted = $this->getData('groups/schedule/fields/' . $field . '/value');
        if ($posted !== null) {
            return (string) $posted;
        }

        return (string) $this->_config->getValue('egsn_si/schedule/' . $field);
    }
}

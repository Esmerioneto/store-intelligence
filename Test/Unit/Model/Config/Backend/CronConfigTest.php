<?php
/**
 * Esmerio Neto
 *
 * @category Egsn
 * @package Egsn_StoreIntelligence
 *
 * @copyright Copyright (c) 2026 Esmerio Neto.
 *
 * @author Esmerio Neto <esmerioneto@gmail.com>
 */
declare(strict_types=1);

namespace Egsn\StoreIntelligence\Test\Unit\Model\Config\Backend;

use Egsn\StoreIntelligence\Model\Config\Backend\CronConfig;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;

class CronConfigTest extends TestCase
{
    /**
     * Make subject.
     *
     * @param WriterInterface $configWriter
     * @param string|null $storedTime
     * @return CronConfig
     */
    private function makeSubject(WriterInterface $configWriter, ?string $storedTime = null): CronConfig
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path) => $path === 'egsn_si/schedule/time' ? $storedTime : null
        );

        $context = $this->createMock(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createMock(ManagerInterface::class));

        return new CronConfig(
            $context,
            $this->createMock(Registry::class),
            $scopeConfig,
            $this->createMock(TypeListInterface::class),
            $configWriter
        );
    }

    /**
     * Test daily expression composed from posted time field.
     *
     * @return void
     */
    public function testComposesDailyExpressionFromPostedTime(): void
    {
        $configWriter = $this->createMock(WriterInterface::class);
        $configWriter->expects($this->once())
            ->method('save')
            ->with(CronConfig::CRON_STRING_PATH, '30 2 * * *');

        $subject = $this->makeSubject($configWriter);
        $subject->setData('path', 'egsn_si/schedule/frequency');
        $subject->setData('groups', ['schedule' => ['fields' => ['time' => ['value' => ['02', '30', '00']]]]]);
        $subject->setValue('D');
        $subject->afterSave();
    }

    /**
     * Test weekly expression runs on Monday.
     *
     * @return void
     */
    public function testComposesWeeklyExpression(): void
    {
        $configWriter = $this->createMock(WriterInterface::class);
        $configWriter->expects($this->once())
            ->method('save')
            ->with(CronConfig::CRON_STRING_PATH, '0 4 * * 1');

        $subject = $this->makeSubject($configWriter);
        $subject->setData('path', 'egsn_si/schedule/frequency');
        $subject->setData('groups', ['schedule' => ['fields' => ['time' => ['value' => ['04', '00', '00']]]]]);
        $subject->setValue('W');
        $subject->afterSave();
    }

    /**
     * Test monthly expression runs on the 1st using stored time when no form data.
     *
     * @return void
     */
    public function testComposesMonthlyExpressionFromStoredTime(): void
    {
        $configWriter = $this->createMock(WriterInterface::class);
        $configWriter->expects($this->once())
            ->method('save')
            ->with(CronConfig::CRON_STRING_PATH, '15 5 1 * *');

        $subject = $this->makeSubject($configWriter, '05,15,00');
        $subject->setData('path', 'egsn_si/schedule/frequency');
        $subject->setValue('M');
        $subject->afterSave();
    }

    /**
     * Test default time fallback when nothing is posted or stored.
     *
     * @return void
     */
    public function testFallsBackToDefaultTime(): void
    {
        $configWriter = $this->createMock(WriterInterface::class);
        $configWriter->expects($this->once())
            ->method('save')
            ->with(CronConfig::CRON_STRING_PATH, '0 1 * * *');

        $subject = $this->makeSubject($configWriter);
        $subject->setData('path', 'egsn_si/schedule/frequency');
        $subject->setValue('D');
        $subject->afterSave();
    }

    /**
     * Test custom expression with step values is stored as-is.
     *
     * @return void
     */
    public function testCustomExpressionEveryFiveMinutes(): void
    {
        $configWriter = $this->createMock(WriterInterface::class);
        $configWriter->expects($this->once())
            ->method('save')
            ->with(CronConfig::CRON_STRING_PATH, '*/5 * * * *');

        $subject = $this->makeSubject($configWriter);
        $subject->setData('path', 'egsn_si/schedule/cron_expr');
        $subject->setValue('*/5 * * * *');
        $subject->afterSave();
    }

    /**
     * Test lists, ranges and names are accepted.
     *
     * @return void
     */
    public function testCustomExpressionListsRangesAndNames(): void
    {
        $configWriter = $this->createMock(WriterInterface::class);
        $configWriter->expects($this->once())
            ->method('save')
            ->with(CronConfig::CRON_STRING_PATH, '0,30 8-18/2 * jan-jun mon-fri');

        $subject = $this->makeSubject($configWriter);
        $subject->setData('path', 'egsn_si/schedule/cron_expr');
        $subject->setValue('0,30 8-18/2 * jan-jun mon-fri');
        $subject->afterSave();
    }

    /**
     * Test @shortcuts are translated to standard expressions.
     *
     * @return void
     */
    public function testShortcutIsTranslated(): void
    {
        $configWriter = $this->createMock(WriterInterface::class);
        $configWriter->expects($this->once())
            ->method('save')
            ->with(CronConfig::CRON_STRING_PATH, '0 0 * * *');

        $subject = $this->makeSubject($configWriter);
        $subject->setData('path', 'egsn_si/schedule/cron_expr');
        $subject->setValue('@daily');
        $subject->afterSave();
    }

    /**
     * Test @reboot is rejected.
     *
     * @return void
     */
    public function testRebootShortcutIsRejected(): void
    {
        $subject = $this->makeSubject($this->createMock(WriterInterface::class));
        $subject->setData('path', 'egsn_si/schedule/cron_expr');
        $subject->setValue('@reboot');

        $this->expectException(ValidatorException::class);
        $subject->beforeSave();
    }

    /**
     * Test malformed expressions are rejected.
     *
     * @return void
     */
    public function testInvalidExpressionIsRejected(): void
    {
        $subject = $this->makeSubject($this->createMock(WriterInterface::class));
        $subject->setData('path', 'egsn_si/schedule/cron_expr');
        $subject->setValue('every 5 minutes');

        $this->expectException(ValidatorException::class);
        $subject->beforeSave();
    }

    /**
     * Test custom expression posted in the form overrides frequency composition.
     *
     * @return void
     */
    public function testPostedCustomExpressionOverridesFrequency(): void
    {
        $configWriter = $this->createMock(WriterInterface::class);
        $configWriter->expects($this->once())
            ->method('save')
            ->with(CronConfig::CRON_STRING_PATH, '*/10 * * * *');

        $subject = $this->makeSubject($configWriter);
        $subject->setData('path', 'egsn_si/schedule/frequency');
        $subject->setData('groups', ['schedule' => ['fields' => ['cron_expr' => ['value' => '*/10 * * * *']]]]);
        $subject->setValue('D');
        $subject->afterSave();
    }

    /**
     * Test clearing the custom expression falls back to Frequency + Start Time.
     *
     * @return void
     */
    public function testClearedCustomExpressionFallsBackToFrequency(): void
    {
        $configWriter = $this->createMock(WriterInterface::class);
        $configWriter->expects($this->once())
            ->method('save')
            ->with(CronConfig::CRON_STRING_PATH, '0 1 * * *');

        $subject = $this->makeSubject($configWriter);
        $subject->setData('path', 'egsn_si/schedule/cron_expr');
        $subject->setData('groups', ['schedule' => ['fields' => ['frequency' => ['value' => 'D']]]]);
        $subject->setValue('');
        $subject->afterSave();
    }
}

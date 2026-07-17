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

namespace Egsn\StoreIntelligence\Test\Unit\Collector\Errors;

use Egsn\StoreIntelligence\Collector\Errors\ExceptionLogCollector;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use PHPUnit\Framework\TestCase;

class ExceptionLogCollectorTest extends TestCase
{
    /**
     * Test collect with no log file returns ok.
     *
     * @return void
     */
    public function testCollectWithNoLogFileReturnsOk(): void
    {
        $dir = $this->createMock(ReadInterface::class);
        $dir->method('isExist')->willReturn(false);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryRead')->willReturn($dir);

        $collector = new ExceptionLogCollector($filesystem);
        $result    = $collector->collect();

        $this->assertSame('exception_log', $result->getCollectorCode());
        $this->assertSame('errors', $result->getCategory());
        $this->assertSame('ok', $result->getStatus());
        $this->assertSame(0, $result->getSummary()['count']);
    }

    /**
     * Test collect with errors returns critical.
     *
     * @return void
     */
    public function testCollectWithErrorsReturnsCritical(): void
    {
        $logContent = implode("\n", array_fill(
            0,
            60,
            '[2026-06-25 03:00:00] main.CRITICAL: PaymentException: Error'
        ));

        $tmpFile = tempnam(sys_get_temp_dir(), 'egsn_log_test');
        file_put_contents($tmpFile, $logContent);

        $dir = $this->createMock(ReadInterface::class);
        $dir->method('isExist')->willReturn(true);
        $dir->method('getAbsolutePath')->willReturn($tmpFile);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryRead')->willReturn($dir);

        $collector = new ExceptionLogCollector($filesystem);
        $result    = $collector->collect();

        unlink($tmpFile);

        $this->assertSame('critical', $result->getStatus());
        $this->assertGreaterThan(0, $result->getSummary()['count']);
    }
}

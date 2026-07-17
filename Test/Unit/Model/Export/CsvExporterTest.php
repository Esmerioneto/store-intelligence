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

namespace Egsn\StoreIntelligence\Test\Unit\Model\Export;

use Egsn\StoreIntelligence\Model\Export\CsvExporter;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\File\WriteInterface as FileWriteInterface;
use PHPUnit\Framework\TestCase;

class CsvExporterTest extends TestCase
{
    /**
     * Test export writes csv and returns content.
     *
     * @return void
     */
    public function testExportWritesCsvAndReturnsContent(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('fetchRow')
            ->willReturn(['id' => 5, 'score' => 80, 'ran_at' => '2026-01-01', 'summary' => 'ok']);
        $adapter->method('fetchAll')->willReturn([]);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        $written = [];
        $stream = $this->createMock(FileWriteInterface::class);
        $stream->method('writeCsv')->willReturnCallback(function (array $row) use (&$written): int {
            $written[] = $row;
            return 0;
        });

        $dir = $this->createMock(WriteInterface::class);
        $dir->method('openFile')->willReturn($stream);
        $dir->method('readFile')->willReturn("csv-content");
        $dir->expects($this->once())->method('delete');

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($dir);

        $csv = (new CsvExporter($resource, $filesystem))->export(5);

        $this->assertSame('csv-content', $csv);
        $this->assertSame(['Store Intelligence Export'], $written[0]);
        $this->assertContains(['Analysis ID', 5, 'Score', 80, 'Date', '2026-01-01'], $written);
    }

    /**
     * Test export throws when analysis not found.
     *
     * @return void
     */
    public function testExportThrowsWhenAnalysisNotFound(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('fetchRow')->willReturn(false);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturnArgument(0);

        $this->expectException(NoSuchEntityException::class);
        (new CsvExporter($resource, $this->createMock(Filesystem::class)))->export(999);
    }
}

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

namespace Egsn\StoreIntelligence\Collector\Errors;

use Egsn\StoreIntelligence\Collector\CollectionResult;
use Egsn\StoreIntelligence\Collector\CollectorInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File as FileIo;
use Psr\Log\LoggerInterface;

class IntegrationErrorsCollector implements CollectorInterface
{
    use LogFileTailTrait;

    private const FILE_PATTERNS = [
        '*payment*.log',
        '*api*.log',
        '*erp*.log',
        '*integration*.log',
    ];

    private const MAX_LINES = 100;

    /**
     * Constructor.
     *
     * @param Filesystem $filesystem
     * @param LoggerInterface $logger
     * @param FileIo $fileIo
     */
    public function __construct(
        private readonly Filesystem      $filesystem,
        private readonly LoggerInterface $logger,
        private readonly FileIo          $fileIo
    ) {
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return 'integration_errors';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Erros de Integração';
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
        $dir          = $this->filesystem->getDirectoryRead(DirectoryList::LOG);
        $checkedFiles = [];
        $totalErrors  = 0;

        try {
            $allFiles = $dir->read('.');
        } catch (\Throwable $e) {
            $allFiles = [];
        }

        $matchedFiles = [];
        foreach ($allFiles as $filePath) {
            $fileName = (string) ($this->fileIo->getPathInfo($filePath)['basename'] ?? '');
            foreach (self::FILE_PATTERNS as $pattern) {
                if (fnmatch($pattern, $fileName)) {
                    $matchedFiles[] = $fileName;
                    break;
                }
            }
        }

        foreach ($matchedFiles as $fileName) {
            try {
                $lines      = $this->tailFile($dir->getAbsolutePath($fileName), self::MAX_LINES);
                $errorCount = 0;

                foreach ($lines as $line) {
                    if (stripos($line, 'ERROR') !== false || stripos($line, 'CRITICAL') !== false) {
                        $errorCount++;
                    }
                }

                $checkedFiles[] = ['name' => $fileName, 'errors' => $errorCount];
                $totalErrors    += $errorCount;
            } catch (\Throwable $e) {
                $this->logger->debug('[StoreIntelligence] integration_errors: file skipped: ' . $e->getMessage());
            }
        }

        $filesChecked = count($checkedFiles);
        $status       = $totalErrors === 0 ? 'ok' : ($totalErrors < 10 ? 'warning' : 'critical');

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: [
                'files_checked' => $filesChecked,
                'total_errors'  => $totalErrors,
                'files'         => $checkedFiles,
            ],
            score: max(0, 100 - min(100, $totalErrors * 5))
        );
    }
}

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

class ExceptionLogCollector implements CollectorInterface
{
    use LogFileTailTrait;

    private const LOG_FILE  = 'exception.log';
    private const MAX_LINES = 5000;

    /**
     * Constructor.
     *
     * @param Filesystem $filesystem
     */
    public function __construct(private readonly Filesystem $filesystem)
    {
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return 'exception_log';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Erros de Exceção';
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
        $dir = $this->filesystem->getDirectoryRead(DirectoryList::LOG);

        if (!$dir->isExist(self::LOG_FILE)) {
            return new CollectionResult(
                $this->getCode(),
                $this->getCategory(),
                'ok',
                ['count' => 0, 'note' => 'Log file not found']
            );
        }

        $lines = $this->tailFile($dir->getAbsolutePath(self::LOG_FILE), self::MAX_LINES);

        $errors = [];
        foreach ($lines as $line) {
            if (preg_match('/(\w+Exception|\w+Error)/', $line, $m)) {
                $type          = $m[1];
                $errors[$type] = ($errors[$type] ?? 0) + 1;
            }
        }

        arsort($errors);
        $total  = array_sum($errors);
        $top10  = array_slice($errors, 0, 10, true);
        $status = $total === 0 ? 'ok' : ($total < 50 ? 'warning' : 'critical');

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: $status,
            summary: ['count' => $total, 'top_errors' => $top10],
            score: max(0, 100 - min(100, (int) ($total / 5)))
        );
    }
}

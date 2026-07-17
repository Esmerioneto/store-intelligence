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

trait LogFileTailTrait
{
    /**
     * Read the last $lines lines of a file without loading the entire content into memory.
     *
     * @param string $path
     * @param int $lines
     * @return array
     */
    private function tailFile(string $path, int $lines): array
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        $start      = max(0, $totalLines - $lines);
        $result     = [];
        $file->seek($start);
        while (!$file->eof()) {
            $result[] = rtrim((string) $file->current());
            $file->next();
        }
        return $result;
    }
}

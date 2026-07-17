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

namespace Egsn\StoreIntelligence\Model\Export;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;

class CsvExporter
{
    /**
     * Constructor.
     *
     * @param ResourceConnection $resource
     * @param Filesystem $filesystem
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Filesystem         $filesystem
    ) {
    }

    /**
     * Export.
     *
     * @param int $analysisId
     * @return string
     */
    public function export(int $analysisId): string
    {
        $conn     = $this->resource->getConnection();
        $aTable   = $this->resource->getTableName('egsn_si_analysis');
        $crTable  = $this->resource->getTableName('egsn_si_collector_result');
        $recTable = $this->resource->getTableName('egsn_si_recommendation');

        $analysis = $conn->fetchRow("SELECT * FROM {$aTable} WHERE id = ?", [$analysisId]);
        if ($analysis === false) {
            throw new \Magento\Framework\Exception\NoSuchEntityException(
                __('Analysis with ID %1 not found.', $analysisId)
            );
        }
        $results  = $conn->fetchAll("SELECT * FROM {$crTable} WHERE analysis_id = ?", [$analysisId]);
        $recs     = $conn->fetchAll("SELECT * FROM {$recTable} WHERE analysis_id = ?", [$analysisId]);

        $rows   = [];
        $rows[] = ['Store Intelligence Export'];
        $rows[] = ['Analysis ID', $analysisId, 'Score', $analysis['score'] ?? '', 'Date', $analysis['ran_at'] ?? ''];
        $rows[] = ['Summary', $analysis['summary'] ?? ''];
        $rows[] = [];
        $rows[] = ['=== COLLECTOR RESULTS ==='];
        $rows[] = ['Collector', 'Category', 'Status', 'Score'];
        foreach ($results as $r) {
            $rows[] = [$r['collector'], $r['category'], $r['status'], $r['score'] ?? ''];
        }
        $rows[] = [];
        $rows[] = ['=== RECOMMENDATIONS ==='];
        $rows[] = ['Priority', 'Category', 'Collector', 'Title', 'Action', 'Estimated Impact'];
        foreach ($recs as $r) {
            $rows[] = [
                $r['priority'], $r['category'], $r['collector'],
                $r['title'], $r['action'], $r['estimated_impact'] ?? '',
            ];
        }
        $rows[] = [];
        $rows[] = ['=== AI PROMPT ==='];
        $rows[] = [$analysis['ai_prompt'] ?? ''];

        $dir      = $this->filesystem->getDirectoryWrite(DirectoryList::TMP);
        $fileName = 'egsn_si_export_' . uniqid('', true) . '.csv';
        $stream   = $dir->openFile($fileName, 'w+');
        foreach ($rows as $row) {
            $stream->writeCsv($row);
        }
        $stream->close();

        $csv = $dir->readFile($fileName);
        $dir->delete($fileName);

        return $csv;
    }
}

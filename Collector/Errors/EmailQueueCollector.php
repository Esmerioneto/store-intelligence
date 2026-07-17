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
use Magento\Framework\App\ResourceConnection;

class EmailQueueCollector implements CollectorInterface
{
    private const TABLE_CANDIDATES = [
        'queue_message',
        'email_message_queue',
    ];

    /**
     * Constructor.
     *
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return 'email_queue';
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Fila de E-mails com Falha';
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
        $connection = $this->resourceConnection->getConnection();

        foreach (self::TABLE_CANDIDATES as $candidate) {
            $table = $this->resourceConnection->getTableName($candidate);

            try {
                $count = (int) $connection->fetchOne("SELECT COUNT(*) FROM {$table}");

                return new CollectionResult(
                    collectorCode: $this->getCode(),
                    category: $this->getCategory(),
                    status: $count > 0 ? 'warning' : 'ok',
                    summary: ['count' => $count, 'table' => $candidate],
                    score: $count === 0 ? 100 : max(0, 100 - min(100, $count * 2))
                );
            } catch (\Throwable $e) {
                // Table does not exist, try the next candidate
                continue;
            }
        }

        return new CollectionResult(
            collectorCode: $this->getCode(),
            category: $this->getCategory(),
            status: 'ok',
            summary: ['count' => 0, 'note' => 'Email queue table not found: ' . $e->getMessage()],
            score: 100
        );
    }
}

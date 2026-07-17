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

namespace Egsn\StoreIntelligence\Console\Command;

use Egsn\StoreIntelligence\Model\CollectorPool;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Roda todos os collectors contra o banco real e falha (exit 1) se algum
 * retornar um note de erro — pega SQL quebrado que os testes unitários
 * (que mockam o banco) não conseguem detectar.
 */
class SelfCheckCommand extends Command
{
    /**
     * Constructor.
     *
     * @param CollectorPool $collectorPool
     * @param State $appState
     */
    public function __construct(
        private readonly CollectorPool $collectorPool,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    /**
     * Configure.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('egsn:si:selfcheck')
            ->setDescription('Roda todos os collectors do Store Intelligence e reporta falhas de coleta');
        parent::configure();
    }

    /**
     * Execute.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException) {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        }

        $failures = 0;
        foreach ($this->collectorPool->getAll() as $collector) {
            try {
                $result = $collector->collect();
                $note   = (string) ($result->getSummary()['note'] ?? '');
                $broken = $note !== '' && preg_match('/SQLSTATE|not accessible|not found|Could not/i', $note);
                $mark   = $broken ? '<error>FALHA</error>' : '<info>' . strtoupper($result->getStatus()) . '</info>';
                $output->writeln(sprintf('%-28s %s %s', $result->getCollectorCode(), $mark, $note));
                if ($broken) {
                    $failures++;
                }
            } catch (\Throwable $e) {
                $output->writeln(sprintf('%-28s <error>EXCEÇÃO</error> %s', $collector->getCode(), $e->getMessage()));
                $failures++;
            }
        }

        $output->writeln('');
        $output->writeln($failures === 0
            ? '<info>Todos os collectors OK.</info>'
            : "<error>{$failures} collector(s) com falha de coleta.</error>");

        return $failures === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}

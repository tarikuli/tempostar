<?php
declare(strict_types=1);

// This command exports inventory data to a CSV and uploads it to the TEMPOSTAR FTP server.
// It is intended to be run from the command line or via cron, and uses configuration and logging services.

namespace Tarikul\TempostarConnector\Console\Command;

use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tarikul\TempostarConnector\Logger\Tempostar\Logger as Tempostarlogger;
use Tarikul\TempostarConnector\Model\Config;
use Tarikul\TempostarConnector\Services\ExportInventory;
class InventoryExport extends Command
{
    public function __construct(
        protected State           $state,
        protected Config          $config,
        protected Tempostarlogger $tempostarlogger,
        protected ExportInventory $exportInventory,
    ) {
        parent::__construct();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        if (!$this->config->isTempostarConnectorEnabled()) {
            $this->tempostarlogger->error(
                'Cron is not executing because HBJI TEMPOSTAR config is not enabled.'
            );
            return false;
        }

        try {
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);
            $this->exportInventory->execute();
            return Command::SUCCESS;
        } catch (\Exception $execption) {
            $this->tempostarlogger->error('Error: ' . $execption->getMessage());
            $output->writeln('Error: ' . $execption->getMessage());
        }
    }

    /**
     * Configure command
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName("tempostarconnector:inventory:export");
        $this->setDescription("Inventory export to TEMPOSTAR ftp.");
        parent::configure();
    }
}

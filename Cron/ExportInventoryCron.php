<?php
declare(strict_types=1);

/**
 * This cron job exports inventory data to the TEMPOSTAR FTP server on a schedule.
 * It checks configuration, logs errors, and delegates the export logic to the ExportInventory service.
 */

namespace Tarikul\TempostarConnector\Cron;

use Tarikul\TempostarConnector\Logger\Tempostar\Logger as Tempostarlogger;
use Tarikul\TempostarConnector\Model\Config;
use Tarikul\TempostarConnector\Services\exportInventory;
use Tarikul\TempostarConnector\Helper\Data as HelperData;

class ExportInventoryCron
{
    /**
     * ExportInventoryCron constructor.
     *
     * @param Config $config Module configuration for checking if export is enabled
     * @param Tempostarlogger $tempostarlogger Logger for error and info messages
     * @param ExportInventory $exportInventory Service that performs the actual inventory export
     * @param HelperData $helperData Helper for additional utility functions
     */
    public function __construct(
        protected Config $config,
        protected Tempostarlogger $tempostarlogger,
        protected ExportInventory $exportInventory,
        protected HelperData $helperData
    ) {
        // Constructor simply assigns dependencies for use in the cron job
    }

    /**
     * Executes the scheduled inventory export cron job.
     *
     * Checks if the TempostarConnector module is enabled in configuration.
     * If enabled, calls the ExportInventory service to perform the export.
     * Logs errors if the module is disabled or if any exception occurs during export.
     *
     * @return void|false Returns false if the module is disabled, otherwise void.
     */
    public function execute()
    {
        if (!$this->config->isTempostarConnectorEnabled()) {
            $this->tempostarlogger->error(
                'Cron is not executing because HBJI TEMPOSTAR config is not enabled.'
            );
            return false;
        }

        try {
            $this->exportInventory->execute();
        } catch (\Exception $execption) {
            $this->tempostarlogger->error('Error: ' . $execption->getMessage());
        }
    }
}

<?php
declare(strict_types=1);

/**
 * This cron job imports orders from the TEMPOSTAR FTP server on a schedule.
 * It checks configuration, logs errors, and delegates the import logic to the ImportOrder service.
 */

namespace Tarikul\TempostarConnector\Cron;

use Tarikul\TempostarConnector\Logger\Tempostar\Logger as Tempostarlogger;
use Tarikul\TempostarConnector\Model\Config;
use Tarikul\TempostarConnector\Services\ImportOrder;
use Tarikul\TempostarConnector\Helper\Data as HelperData;

class OrderImport
{
    public function __construct(
        protected Config $config,
        protected Tempostarlogger $tempostarlogger,
        protected ImportOrder $importOrder,
        protected HelperData $helperData
    ) {

    }

    /**
     * Execute the cron
     */
    public function execute()
    {
        if (!$this->config->isTempostarConnectorEnabled()) {
            $this->tempostarlogger->error(
                'Export orders CSV Cron is not executing because TEMPOSTAR config is not enabled.'
            );
            return false;
        }

        try {
            $this->importOrder->execute();
        } catch (\Exception $execption) {
            $this->tempostarlogger->error('Error: ' . $execption->getMessage());
        }
    }
}

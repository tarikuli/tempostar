<?php
declare(strict_types=1);

/**
 * This model handles the SFTP connection to the TEMPOSTAR server using configuration values.
 * Used by import/export services to transfer files between Magento and TEMPOSTAR.
 */

namespace Tarikul\TempostarConnector\Model;

use Tarikul\TempostarConnector\Model\Config;
use Psr\Log\LoggerInterface;

class Connector extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Connector constructor.
     *
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected Config $config,
        protected LoggerInterface $logger
    ) {}

    /**
     * SFTP connection
     *
     * @return \phpseclib3\Net\SFTP
     * @throws \Exception
     */
    public function ftpConnection()
    {
        $open = new \phpseclib3\Net\SFTP($this->config->getTempostarHost());
        $open->login(
            $this->config->getTempostarUsername(),
            $this->config->getTempostarPassword(),

        );
        return $open;
    }
}

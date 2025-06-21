<?php
// Custom Monolog handler for writing TempostarConnector logs to a specific file in var/log/tempostar/.
// Used for logging import/export operations and errors.

declare(strict_types=1);

namespace Hanesce\TempostarConnector\Logger\Tempostar;

use Magento\Framework\Filesystem;
use Magento\Framework\Logger\Handler\Base;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Monolog\Logger;

class Handler extends Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * @var TimezoneInterface
     */
    protected $_localeDate;

    /**
     * Handler constructor.
     * @param DriverInterface $filesystem
     * @param Filesystem $corefilesystem
     * @param TimezoneInterface $localeDate
     */
    public function __construct(
        DriverInterface $filesystem,
        Filesystem $corefilesystem,
        TimezoneInterface $localeDate
    ) {
        $this->_localeDate = $localeDate;
        $corefilesystem = $corefilesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);
        $logpath = $corefilesystem->getAbsolutePath('log/');

        $filename = 'tempostar/tempostar_'. $this->getTimeStamp() .'.log';
        $filepath = $logpath . $filename;
        parent::__construct(
            $filesystem,
            $filepath
        );
    }

    /**
     * Get current timestamp
     *
     * @return string
     */
    public function getTimeStamp()
    {
        return $this->_localeDate->date()->format('Y_m_d');
    }
}

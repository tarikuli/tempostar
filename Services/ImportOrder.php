<?php
declare(strict_types=1);

namespace Tarikul\TempostarConnector\Services;

// This service handles importing order data from the TEMPOSTAR FTP server into Magento using CSV files.
// It is used by both the CLI command and the scheduled cron job for order import.

use Tarikul\TempostarConnector\Helper\Data;
use Tarikul\TempostarConnector\Logger\Tempostar\Logger as Tempostarlogger;
use Tarikul\TempostarConnector\Model\Config;
use Tarikul\TempostarConnector\Model\Connector;
use Tarikul\TempostarConnector\Model\SyncOrderData;
use Tarikul\TempostarConnector\Helper\Data as HelperData;

class ImportOrder
{
    private $_dir;
    private $_filesystem;
    protected $syncOrderData;

    protected $connector;

    public function __construct(
        Connector                                   $connector,
        protected Config                            $config,
        protected HelperData                        $helperData,
        protected Tempostarlogger                   $tempostarLogger,
        \Magento\Framework\Filesystem\DirectoryList $dir,
        \Magento\Framework\Filesystem               $filesystem,
        SyncOrderData                               $syncOrderData,
    )
    {
        $this->_dir = $dir;
        $this->_filesystem = $filesystem;
        $this->connector = $connector;
        $this->syncOrderData = $syncOrderData;
    }

    /**
     * Execute the export order to ERP
     *
     * @return void
     * @throws \Exception
     */
    public function execute()
    {
        // Create FTP connection
        $sftp = $this->connector->ftpConnection();
        $sourceOrderFtpPath = $this->config->getOrderImportPath();
        $sourceOrderArchiveFtpPath = $this->config->getOrderImportArchivePath();
        $orderUpdateFtpPath = $this->config->getOrderUpdatePath();
        $orderUpdateArchiveFtpPath = $this->config->getOrderUpdateArchivePath();
        // Initial local dir for import.
        $fileSavePath = $this->_dir->getPath('var') . "/log/tmpo/order/";
        $fileList = [];
        $updateFileList = [];

        // Check if directory exists, if not create it
        if (!is_dir($fileSavePath)) {
            mkdir($fileSavePath, 0775, true);
        }

        if ($sftp) {
            $sftp->chdir($sourceOrderFtpPath);
            foreach ($sftp->nlist() as $file) {
                if ($file != "." && $file != "..") {
                    // Download CSV file from remote FTP to local
                    $sftp->get($sourceOrderFtpPath . $file, $fileSavePath . $file);
                    // Wait 5 sec for download csv file from remote to local
                    sleep(5);
                    // Move file after download.
                    $sftp->rename($sourceOrderFtpPath.$file, $sourceOrderArchiveFtpPath.$file);
                    $fileList[$file] = $fileSavePath . $file;
                }
            }

            foreach ($fileList as $file) {
                // Read Data from csv file.
                $csvData = $this->convertCsvDatatoArray($file);
                // Create order.
                $this->syncOrderData->createNewOrder($csvData, $file);
                // Delete the csv file from local.
                if (file_exists($file) && !is_dir($file)) {
                    unlink($file);
                }

            }

            #############
            $sftp->chdir($orderUpdateFtpPath);
            foreach ($sftp->nlist() as $file) {
                if ($file != "." && $file != "..") {
                    // Download CSV file from remote FTP to local
                    $sftp->get($orderUpdateFtpPath . $file, $fileSavePath . $file);
                    // Wait 5 sec for download csv file from remote to local
                    sleep(5);
                    // Move file after download.
                    $sftp->rename($orderUpdateFtpPath.$file, $orderUpdateArchiveFtpPath.$file);
                    $updateFileList[$file] = $fileSavePath . $file;
                }
            }

            foreach ($updateFileList as $file) {
                // Read Data from csv file.
                $csvData = $this->convertCsvDatatoArray($file);
                // Create order.
                $this->syncOrderData->updateOrder($csvData, $file);
                // Delete the csv file from local.
                if (file_exists($file) && !is_dir($file)) {
                    unlink($file);
                }

            }
            #############
        } else {
            throw new Exception(__('Connection Failed'));
        }
    }

    /**
     * Read the CSV file and return the header and body data.
     *
     * @param string $filePath
     * @return array
     * @throws \Exception
     */
    public function convertCsvDatatoArray(string $filePath): array
    {
        // Check if the file exists
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \Exception("File not found or not readable: {$filePath}");
            return [];
        }

        $header = null;
        $csvData = [];
        $groupedArray = [];
        try {
            // Open the file for reading
            if (($handle = fopen($filePath, 'r')) !== false) {
                // Loop through each line of the file
                while (($row = fgetcsv($handle, 10000, ',')) !== false) {
                    if (!$header) {
                        // First row, treat it as the header
                        $header = mb_convert_encoding($row, "UTF-8");
                    } else {
                        // Append the row to the data array
                        $csvData[] = mb_convert_encoding($row, "UTF-8");
                    }
                }
                // Close the file after reading
                fclose($handle);
            } else {
                throw new \Exception("Unable to open file for reading: {$filePath}");
            }
            // Make the first row as indexes of the array values
            $indexedData = [];
            foreach ($csvData as $row) {
                $indexedRow = [];
                foreach ($row as $key => $value) {
                    $indexedRow[(trim($header[$key]))] = $value;
                }
                $indexedData[] = $indexedRow;
            }
            // Example usage: print the array with the first row as indexes of array values
            foreach ($indexedData as $item) {
                if (!isset($item["ショップ受注番号"])) {
                    continue;
                }
                $uniqueValue = $item["ショップ受注番号"]; // Shop order number
                if (!isset($groupedArray[$uniqueValue])) {
                    $groupedArray[$uniqueValue] = array();
                }
                $groupedArray[$uniqueValue][] = $item;
            }
            // Return grouped array
            return $groupedArray;

        } catch (FileSystemException $e) {
            $this->logger->info($e->getMessage());
            return [];
        }
    }
}

<?php

namespace Hanesce\TempostarConnector\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\File\Csv;
use Magento\Sales\Model\Order\Shipment;
use Hanesce\TempostarConnector\Helper\Data as HelperData;
use Hanesce\TempostarConnector\Logger\Tempostar\Logger as Tempostarlogger;
use Hanesce\TempostarConnector\Model\Config;
use Hanesce\TempostarConnector\Model\Connector;

// This observer exports shipment data to a CSV file when a shipment event occurs in Magento.
// The CSV is then uploaded to the TEMPOSTAR FTP server for external processing.

class ExportShipmentCsv implements ObserverInterface
{
    /**
     * @var Filesystem\Directory\WriteInterface
     */
    protected $directory;

    public function __construct(
        Filesystem                $filesystem,
        protected HelperData      $helperData,
        protected Tempostarlogger $tempostarlogger,
        protected Config          $config,
        protected Connector       $connector,
        protected DirectoryList   $dir,
        protected Csv             $csvProcessor
    )
    {
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var Shipment $shipment */
            $shipment = $observer->getEvent()->getShipment();
            $order = $shipment->getOrder();

            $data = [
                ['システム受注番号', '配送方法コード', '出荷日', '出荷確定日', '配送日', '荷物番号']
            ];
            $externalOrderAdditionInfo =[];
            // Check is Rakuten Order
            if ($order->getOrderType() == "rakuten") {
                // Shipping date is the creation date of the shipment
                $shippingDate = date("Y/m/d h:i", strtotime($shipment->getCreatedAt()));
                // Shipping confirmation date is the updated date of the shipment
                $confirmationDate = date("Y/m/d h:i", strtotime($shipment->getUpdatedAt()));
                $systemOrderNumber = '';
                $externalOrderAdditionInfoJson = $order->getExternalOrderAdditionalInformation();
                if (!is_null($externalOrderAdditionInfoJson)) {
                    $externalOrderAdditionInfo = mb_convert_encoding(json_decode($externalOrderAdditionInfoJson, true), "UTF-8");
                    if(!isset($externalOrderAdditionInfo['配送方法コード'])){
                        $externalOrderAdditionInfo['配送方法コード'] = "Not found";
                    }
                    if(isset($externalOrderAdditionInfo['システム受注番号'])){
                        $systemOrderNumber = $externalOrderAdditionInfo['システム受注番号'];
                    }
                }

                $track_number = '';
                foreach ($shipment->getAllTracks() as $track) {
                    $track_number = $track->getTrackNumber()?$track->getTrackNumber():'';
                    break;
                }
                $data[] = [
                    $systemOrderNumber?$systemOrderNumber.'_1':'',
                    $externalOrderAdditionInfo['配送方法コード'],
                    $shippingDate,
                    $confirmationDate,
                    $confirmationDate,
                    $track_number
                ];
                $this->exportCsv($data);
            }
        } catch (\Exception $e) {
            $this->tempostarlogger->error($e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * Export data to CSV
     *
     * @param array $data
     */
    protected function exportCsv(array $availableShipmentToExport)
    {
        $filename = 'ordership' . date('Ymdhis') . 'utf8';
        $csvFilename = $filename.'.csv';
        $lockFilename = $filename.'.lock';
        $open = $this->connector->ftpConnection();
        if ($open) {
            $shipmentCsvContent = $this->helperData->generateCsvContentFromArray($availableShipmentToExport, 1);
            // save csv content to file in sftp server
            $open->put($this->config->getExportFulfillmentPath() . $csvFilename, $shipmentCsvContent);
            // create .lock file in sftp server for security
            $open->put($this->config->getExportFulfillmentPath() . $lockFilename, '');
        } else {
            throw new Exception(__('Connection Failed'));
        }

    }
}

<?php
declare(strict_types=1);

/**
 * This service handles exporting inventory data from Magento to a CSV file and uploading it to the TEMPOSTAR FTP server.
 * It is used by both the CLI command and the scheduled cron job for inventory export.
 */

namespace Tarikul\TempostarConnector\Services;

use Magento\Framework\File\Csv;
use Tarikul\TempostarConnector\Helper\Data as HelperData;
use Tarikul\TempostarConnector\Logger\Tempostar\Logger as Tempostarlogger;
use Tarikul\TempostarConnector\Model\Config;
use Tarikul\TempostarConnector\Model\Connector;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;

class ExportInventory
{
    protected $lockFilePath;
    public function __construct(
        protected HelperData      $helperData,
        protected Tempostarlogger $tempostarlogger,
        protected Config          $config,
        protected Connector       $connector,
        protected Csv             $csvProcessor,
        protected ProductRepositoryInterface $productRepository,
        protected SearchCriteriaBuilder $searchCriteriaBuilder,
        protected FilterBuilder $filterBuilder,
        protected GetProductSalableQtyInterface $getProductSalableQty,
        protected StockResolverInterface $stockResolver,
        protected StoreManagerInterface $storeManager,
        protected DefaultStockProviderInterface $defaultStockProvider,
    )
    {
        $this->lockFilePath = BP . '/var/log/export_inventory.lock';
    }

    /**
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        if ($this->isLocked()) {
            $this->tempostarlogger->info('ExportInventory is already running.');
            return;
        }

        $this->createLock();
        try {
            $data = [
                ['商品コード', '想定在庫数', '保留在庫数', '更新モード', '更新対象']
            ];
            $salableSimpleProductsWithQtys = $this->getSalableSimpleProductsWithQty();
                foreach ($salableSimpleProductsWithQtys as $salableSimpleProductsWithQty) {
                    $data[] = [
                        $salableSimpleProductsWithQty['sku'],
                        $salableSimpleProductsWithQty['qty'],
                        $salableSimpleProductsWithQty['scecure_qty'],
                        $salableSimpleProductsWithQty['mode'],
                        $salableSimpleProductsWithQty['type'],
                    ];
                }
                $this->exportCsv($data);

        } catch (\Exception $e) {
            $this->tempostarlogger->error($e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        } finally {
            $this->removeLock();
        }
    }

    /**
     * Export data to CSV
     * 
     * @param array $data
     */
    protected function exportCsv(array $availableShipmentToExport)
    {
        $filename = 'stock' . date('Ymdhis') . '_utf8';
        $csvFilename = $filename.'.csv';
        $lockFilename = $filename.'.lock';
        $open = $this->connector->ftpConnection();
        if ($open) {
            $shipmentCsvContent = $this->helperData->generateCsvContentFromArray($availableShipmentToExport,1);
            // save csv content to file in sftp server
            $open->put($this->config->getInventoryPath() . $csvFilename, $shipmentCsvContent);
            // create .lock file in sftp server for security
            $open->put($this->config->getInventoryPath() . $lockFilename, '');
        } else {
            $this->removeLock();
            throw new Exception(__('FTP Connection Failed'));
        }
    }

    protected function getSalableSimpleProductsWithQty()
    {
        try {
            // Filter for simple products
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('type_id', 'simple')
                ->addFilter('publish_to_tps', '1')
                ->create();

            $products = $this->productRepository->getList($searchCriteria)->getItems();
            $salableProducts = [];
            foreach ($products as $product) {
                $salableQty = $this->getProductSalableQty->execute($product->getSku(), 1);
                $scecureQty = round($salableQty * 0.2,0);
                if ($product->isSaleable()) {
                    $salableProducts[] = [
                        'sku' => $product->getSku(),
                        'qty' => $salableQty > 0 ? (string)$salableQty : (string)0,
                        'scecure_qty' => $scecureQty > 0 ? (string)$scecureQty: (string)0,
                        'mode' => (string)1,
                        'type' => (string)1
                    ];
                }
            }
            return $salableProducts;
       } catch (\Exception $e) {
            $this->removeLock();
            $this->tempostarlogger->error($e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }
    }

    protected function isLocked()
    {
        return file_exists($this->lockFilePath);
    }

    protected function createLock()
    {
        file_put_contents($this->lockFilePath, 'locked');
    }

    protected function removeLock()
    {
        if (file_exists($this->lockFilePath)) {
            unlink($this->lockFilePath);
        }
    }
}

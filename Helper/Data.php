<?php
declare(strict_types=1);

/**
 * Helper class for various utility functions used throughout the TempostarConnector module.
 * Provides methods for order, product, and customer data handling, as well as region and stock lookups.
 */

namespace Tarikul\TempostarConnector\Helper;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem\DriverInterface;
use Tarikul\TempostarConnector\Model\Config;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Tarikul\TempostarConnector\Helper\RegionHelper;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

class Data
{
    protected $customerRepository;
    protected $productRepository;
    protected $stockRegistry;
    protected $logger;

    /**
     * Data constructor.
     *
     * @param OrderRepositoryInterface $orderRepository Order repository for order data access
     * @param ResourceConnection $resourceConnection Database resource connection
     * @param SearchCriteriaBuilder $searchCriteriaBuilder For building search criteria
     * @param FilterBuilder $filterBuilder For building filters
     * @param DriverInterface $driver File system driver
     * @param StoreManagerInterface $storeManager Store manager
     * @param RegionHelper $regionHelper Helper for region lookups
     * @param Config $config Module configuration
     * @param TimezoneInterface $timezone Timezone utility
     * @param DateTime $dateTime Date/time utility
     * @param CustomerRepositoryInterface $customerRepository Customer repository
     * @param ProductRepositoryInterface $productRepository Product repository
     * @param StockRegistryInterface $stockRegistry Stock registry
     * @param LoggerInterface $logger Logger for debug/info/error
     */
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected ResourceConnection       $resourceConnection,
        protected SearchCriteriaBuilder    $searchCriteriaBuilder,
        protected FilterBuilder            $filterBuilder,
        protected DriverInterface          $driver,
        protected StoreManagerInterface    $storeManager,
        protected RegionHelper             $regionHelper,
        protected Config                   $config,
        protected TimezoneInterface        $timezone,
        protected DateTime                 $dateTime,
        CustomerRepositoryInterface        $customerRepository,
        ProductRepositoryInterface         $productRepository,
        StockRegistryInterface             $stockRegistry,
        LoggerInterface                    $logger
    )
    {
        $this->customerRepository = $customerRepository;
        $this->productRepository = $productRepository;
        $this->stockRegistry = $stockRegistry;
        $this->logger = $logger;
    }

    /**
     * Convert a multi-dimensional, associative array to CSV data.
     *
     * Opens a temporary in-memory file, writes headers and data rows, and returns the CSV as a string.
     *
     * @param array $data The array of data to convert
     * @param string $forceDoubleQuotes Optional: force double quotes around values
     * @return string CSV text
     * @throws \Exception
     */
    public function generateCsvContentFromArray($data, $forceDoubleQuotes = '')
    {
        # Generate CSV data from array
        $fh = $this->driver->fileOpen('php://temp', 'rw'); # don't create a file, attempt
        # to use memory instead
        # write out the headers
        //$this->driver->filePutCsv($fh, array_values(current($data)));
        $this->driver->filePutCsv($fh, array_values(current($data)), ',', '"', "\r\n");

        $currentRow = 0;
        # write out the data & skip first row
        foreach ($data as $row) {
            if ($currentRow++ == 0) {
                continue;
            }
            if($forceDoubleQuotes) {
                $rowWithQuotes = $this->forceQuotes($row);
                $this->driver->filePutCsv($fh, $rowWithQuotes, ',', '|', "\r\n");
            } else {
                $this->driver->filePutCsv($fh, $row, ',', '"', "\r\n");
            }
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        $this->driver->fileClose($fh);

        $csv = str_replace("'", '"', $csv);
        $csv = str_replace("|", '', $csv);
        $csv = str_replace("\n", "\r\n", $csv);
        return $csv;
    }

    public function forceQuotes($fields) {
        return array_map(function($field) {
            return '"' . str_replace('"', '""', $field) . '"'; // Escape any existing double quotes
        }, $fields);
    }

    /**
     * Checks if the given string contains one or more kanji characters.
     *
     * @param string $str The string to check
     *
     * @return bool Returns true if the string contains one or more kanji characters, otherwise false.
     */
    public function isKanji($str)
    {
        return preg_match('/[\x{4E00}-\x{9FBF}]/u', $str) > 0;
    }

    /**
     * Checks if the given string contains one or more Hiragana characters.
     *
     * @param string $str The string to check
     *
     * @return bool Returns true if the string contains one or more Hiragana characters, otherwise false.
     */
    public function isHiragana($str)
    {
        return preg_match('/[\x{3040}-\x{309F}]/u', $str) > 0;
    }

    /**
     * Checks if the given string contains one or more Katakana characters.
     *
     * @param string $str The string to check
     *
     * @return bool Returns true if the string contains one or more Katakana characters, otherwise false.
     */
    public function isKatakana($str)
    {
        return preg_match('/[\x{30A0}-\x{30FF}]/u', $str) > 0;
    }

    /**
     * @param $str
     * @return bool
     */
    public function isJapanese($str)
    {
        return $this->isKanji($str) || $this->isHiragana($str) || $this->isKatakana($str);
    }

    public function getColorSize($string)
    {
        $pairs = preg_split('/[\s\n]+/', $string);
        $result = array();
        foreach ($pairs as $pair) {
            list($key, $value) = explode(':', $pair);
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * @param $paymentMethodCode
     * @return string
     */
    public function mapPaymentMethod($paymentMethodCode): string
    {
        switch ($paymentMethodCode) {
            case '01':
            case '1':
                $paymentMethodName = "クレジットカード (Credit Card)";
                break;
            case '02':
            case '2':
                $paymentMethodName =  "代金引換 (Cash on Delivery)";
                break;
            case '03':
            case '3':
                $paymentMethodName =  "銀行振込 (Bank Transfer)";
                break;
            case '04':
            case '4':
                $paymentMethodName =  "郵便振替 (Postal Transfer)";
                break;
            case '08':
            case '8':
                $paymentMethodName =  "セブンイレブン決済(前払) (7-Eleven Prepayment)";
                break;
            case '10':
                $paymentMethodName =  "ローソン決済(前払) (Lawson Prepayment)";
                break;
            case '34':
                $paymentMethodName =  "楽天ペイ後払い決済 (Rakuten Pay Deferred Payment)";
                break;
            default:
                $paymentMethodName = "不明な支払い方法 (Unknown payment method)";
                break;
        }
        return $paymentMethodName;
    }

    public function separateName(string $name)
    {
        // Use explode to split the name into an array
        $nameParts = explode(' ', $name);

        // Ensure there are exactly two parts
        if (count($nameParts) !== 2) {
            return false;
//            throw new \Exception('The name must contain exactly one space separating first name and last name.');
        }

        // Assign the parts to first name and last name
        $firstName = $nameParts[0];
        $lastName = $nameParts[1];

        // Return the separated names as an associative array
        return [
            'first_name' => $firstName,
            'last_name' => $lastName
        ];
    }

    public function separateAddress(string $address)
    {
        // Use explode to split the address into an array
        $addressParts = explode(' ', $address);

        // Ensure there are exactly three parts
        if (!isset($addressParts[0]) || !isset($addressParts[1]) || !isset($addressParts[2])) {
            return false;
//            throw new \Exception('The address must contain exactly two spaces separating region, city, and street.');
        }

        // Assign the parts to region, city, and street
        $region = $addressParts[0];
        $city = $addressParts[1];
        if(count($addressParts) > 3){
            $street = $addressParts[2]." ".$addressParts[3];
        }else{
            $street = $addressParts[2];
        }


        // Return the separated parts as an associative array
        return [
            'region' => $region,
            'city' => $city,
            'street' => $street
        ];
    }

    public function getStoreID()
    {
        return $this->storeManager->getStore(1)->getId();
    }
    /**
     * Deletes a file from the remote SFTP server.
     *
     * @param string $remoteFilePath
     * @throws \Exception
     */
    public function deleteRemoteFile($sftp, $remoteFilePath)
    {
        if ($sftp->delete($remoteFilePath)) {
//            return true;
        } else {
            throw new \Exception('Failed to delete remote file: ' . $remoteFilePath);
        }
    }

    /**
     * @param $externalOrderId
     * @return false|mixed|null
     */
    public function getOrderByExternalOrderId($externalOrderId)
    {
        // Create a filter for the custom attribute
        $filter = $this->filterBuilder
            ->setField('external_order_id')
            ->setValue($externalOrderId)
            ->setConditionType('eq')
            ->create();

        // Build the search criteria with the filter
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilters([$filter])
            ->create();

        // Get the list of orders that match the search criteria
        $orderList = $this->orderRepository->getList($searchCriteria);

        // Check if we found any orders
        if ($orderList->getTotalCount() > 0) {
            $orders = $orderList->getItems();
            $orderData = reset($orders);
            return $orderData;
        } else {
            return null;
        }
    }

    /**
     * @param $regionName
     * @param $countryCode
     * @return int
     * @throws \Exception
     */
    public function getRegionId($regionName, $countryCode)
    {
        $region = $this->regionHelper->getRegionIdByNameAndLocale(trim($regionName), trim($countryCode));
        if (!empty($region)) {
            return  $region;
        } else {
//            throw new \Exception('There is no region id for the given name: '. $regionName);
            return false;
        }
    }


    /**
     * Checks if the given string contains one or more kanji characters.
     *
     * @param string $str
     *
     * @return bool
     */
    public function isKanaMultibyte($string) {
        return mb_ereg_match('^[ぁ-んァ-ヶー]+$', $string);
    }

    /**
     * @param $email
     * @return $isEmailNotExists
     */
    public function emailExistOrNot($email)
    {
        try{
            $customer = $this->customerRepository->get($email);

            return $customer->getId();
        } catch(NoSuchEntityException $e) {

            return false;
        }

    }

    /**
     * @param $email
     * @return object
     */
    public function getCustomerData($email)
    {
        return $this->customerRepository->get($email);
    }

    public function customInventoryManage($order)
    {
        if(!$this->config->getBaseInventoryManagement() && $this->config->getCustomInventoryManagement()) {
            try {
                foreach($order->getAllVisibleItems() as $item) {
                    $productId = $this->productRepository->get($item->getSku())->getId();
                    $stockItem = $this->stockRegistry->getStockItem($productId);
                    $updateQty = ($stockItem->getQty()-$item->getQtyOrdered());
                    $stockItem->setData('qty',$updateQty);
                    $stockItem->save();
                }
            } catch (\Exception $exception) {
                $this->logger->error('Error while managing custom stock inventory: ', [
                    'message' => $exception->getMessage(),
                    'orderId' => $order->getIncrementId(),
                ]);
            }
        }
    }

    public function getAdminTimezoneDatetime($inputDateTime)
    {
        // If inputDateTime is not provided, use the current date and time in UTC
        $dateTimeUtc = $inputDateTime ? $inputDateTime : $this->dateTime->gmtDate();

        // Convert the provided date and time to admin-configured timezone
        $adminDateTime = $this->timezone->date(new \DateTime($dateTimeUtc))->format('Y-m-d H:i:s');

        // Return or save $adminDateTime to the database as needed
        return $adminDateTime;
    }
}

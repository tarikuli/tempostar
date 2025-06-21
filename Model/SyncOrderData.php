<?php
// This model is responsible for synchronizing order data from CSV files into Magento orders.
// It is used by the ImportOrder service to create and update orders based on external data.

namespace Tarikul\TempostarConnector\Model;

use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Api\Data\CartItemInterfaceFactory;
use Magento\Quote\Api\Data\PaymentInterfaceFactory;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Tarikul\TempostarConnector\Helper\Data as HelperData;
use Tarikul\TempostarConnector\Logger\Tempostar\Logger as Tempostarlogger;
use Tarikul\HbjiConnector\Helper\Data as HbjiConnectorHelper;
use Magento\Framework\App\State;
use Magento\Sales\Api\OrderRepositoryInterface;


class SyncOrderData
{
    protected $order;
    protected $_filePathName;

    public function __construct(
        protected CartManagementInterface             $cartManagement,
        protected State                               $state,
        protected OrderRepositoryInterface            $orderRepository,
        protected Tempostarlogger                     $tempoStarlogger,
        protected ProductRepositoryInterface          $productRepository,
        protected ProductAttributeRepositoryInterface $productAttributeRepository,
        protected HelperData                          $helperData,
        protected ProductFactory                      $productFactory,
        protected AddressInterfaceFactory             $addressFactory,
        protected CartInterfaceFactory                $cartFactory,
        protected PaymentInterfaceFactory             $paymentFactory,
        protected CartItemInterfaceFactory            $cartItemFactory,
        protected QuoteIdMaskFactory                  $quoteIdMaskFactory,
        protected StoreManagerInterface               $storeManager,
        protected HbjiConnectorHelper                 $hbjiConnectorHelper
    )
    {   }

    /**
     * TO-1
     * @param $csvData
     * @return true
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createNewOrder($csvData, $file)
    {
        $this->_filePathName = basename($file);

        if(empty($csvData)) {
            $this->tempoStarlogger->info("File = ".$this->_filePathName. "Invalid data!");
            return false;
        }

        foreach ($csvData as $rakutenOrderNo => $csvOrderData) {
            $errorList = [];
            $orderData["orderProgressCode"] = $csvOrderData[0]['受注進捗コード']; // Order progress code

            $validCode = [100,200];
            if( !in_array($orderData["orderProgressCode"], $validCode ) || empty($csvOrderData[0]['注文者メールアドレス'])){
                continue;
            }
//$this->jdbg("csvOrderData: ", $csvOrderData[0]);
            $email = $csvOrderData[0]['注文者メールアドレス']; // Order email address
            $orderData["tempoPaymentCode"] = $csvOrderData[0]['支払方法コード']; // Payment method code
            $orderData["orderCreatedAt"] = $csvOrderData[0]['受注日時']; // Order Create Date
            $orderData["shippingMethodCode"] = $csvOrderData[0]['配送方法コード']; // Shipping Method Code
            $orderData["shippingFee"] = $csvOrderData[0]['配送毎配送料']; // Shipping Fee per order
            $orderData["codFee"] = $csvOrderData[0]['配送毎決済手数料']; // Payment COD Fee
            $orderData["systemOrderNumber"] = $csvOrderData[0]['システム受注番号']; //System order number
            $orderData["warehouseComment"] = $this->getWarehouseCommentText($csvOrderData[0]['受注備考']); //warehouse comment
            $customerSelectedDate = $csvOrderData[0]['配送日'];
            $customerSelectedTime = $csvOrderData[0]['配送時間名'];

            $orderData["couponDiscount"] = $csvOrderData[0]['クーポン'] ?? 0; // coupon discount
            $orderData["rewardDiscount"] = $csvOrderData[0]['ポイント'] ?? 0; // reward discount

            if (!empty($customerSelectedDate)) {
                $customerSelectedDate = date('Y-m-d', strtotime($customerSelectedDate));
            }
            if (!empty($customerSelectedTime)) {
                $customerSelectedTime = str_replace('-','～',$customerSelectedTime);
            }
            $orderData["expectedArrivalDate"] = $customerSelectedDate; //Expected Arrival Date expected_arrival_date
            $orderData["instructedArrivalDate"] = $customerSelectedDate; //Instructed Arrival Date requested_arrival_date

            $orderData['expectedArrivalTimeslot'] = $customerSelectedTime;//expected_arrival_timeslot
            $orderData['instructedTimeslot'] = $customerSelectedTime;//instructed_timeslot

            $orderData['tpsCustomerComment'] = $this->getCommentAdditionalShippingInstruction($csvOrderData[0]['受注備考']); // additional shipping instruction from comment

            // Check is order exist by Rakuten external_order_id
            $this->order = $this->helperData->getOrderByExternalOrderId($rakutenOrderNo);

            if (!empty($this->order)) {
                $this->tempoStarlogger->info("File = ".$this->_filePathName. ' and OrderNo: '.$this->order->getIncrementId()." exists");
//                return $this->order;
            } else {
                // If order not exist create a new order.
                $kanjiName = $this->helperData->separateName($csvOrderData[0]['注文者名']); // Customer name
                if(!$kanjiName){
                    $errorList[] = "File = ".$this->_filePathName." External Order ID: ".$rakutenOrderNo." : 'The kanji name must contain exactly one space separating first name and last name. Given = ".$csvOrderData[0]['注文者名'];
//                    $this->tempoStarlogger->error("File = ".$this->_filePathName." External Order ID: ".$rakutenOrderNo." : 'The kanji name must contain exactly one space separating first name and last name.");
                }

                $kanaName = $this->helperData->separateName($csvOrderData[0]['注文者名カナ']); // Customer name kana
                if(!$kanaName){
                    $errorList[] = "File = ".$this->_filePathName." External Order ID: ".$rakutenOrderNo." : 'The kana name must contain exactly one space separating first name and last name. Given = ".$csvOrderData[0]['注文者名カナ'];
//                    $this->tempoStarlogger->error("File = ".$this->_filePathName." External Order ID: ".$rakutenOrderNo." : 'The kana name must contain exactly one space separating first name and last name.");
                }

                $billAddress = $this->helperData->separateAddress($csvOrderData[0]['注文者住所']); // Customer address
                if(isset($billAddress['region'])){
                    $regionId =  $this->helperData->getRegionId($billAddress['region'], 'JP');
                    if(!$regionId){
                        $errorList[] = "File = ".$this->_filePathName." External Order ID: ".$rakutenOrderNo." : Region ID not found.";
//                    $this->tempoStarlogger->error("File = ".$this->_filePathName." External Order ID: ".$rakutenOrderNo." : The address must contain exactly two spaces separating region, city, and street.");
                    }
                }else{
                    $errorList[] = "File = ".$this->_filePathName." External Order ID: ".$rakutenOrderNo." : Region ID not found.";
                }

                if(isset($billAddress['city'])){
                    $billingAddress = [
                        'firstname' => $kanjiName['first_name'],
                        'lastname' => $kanjiName['last_name'],
                        'firstname_kana' => $kanaName['first_name'],
                        'lastname_kana' => $kanaName['last_name'],
                        'street' => $billAddress['street'],
                        'city' => $billAddress['city'],
                        'region' => $billAddress['region'],
                        'region_id' => $regionId,
                        'postcode' => $csvOrderData[0]['注文者郵便番号'],
                        'country_id' => 'JP',
                        'telephone' => $csvOrderData[0]['注文者電話番号'],
                        'save_in_address_book' => 0
                    ];
                }else{
                    $errorList[] = "File = ".$this->_filePathName." External Order ID: ".$rakutenOrderNo." : The billing address must contain exactly two spaces separating region, city, and street.";
                }

                $kanjiName = $this->helperData->separateName($csvOrderData[0]['配送先名']); // Shipping name
                if(!$kanjiName){
                    $errorList[] = "File = ".$this->_filePathName." External Order ID: ".$rakutenOrderNo." : 'The kanji name must contain exactly one space separating first name and last name. Given = ".$csvOrderData[0]['配送先名'];
//                    $this->tempoStarlogger->error("File = ".$this->_filePathName." External Order ID: ".$rakutenOrderNo." : 'The kanji name must contain exactly one space separating first name and last name.");
                }
                $kanaName = $this->helperData->separateName($csvOrderData[0]['配送先名カナ']); // Shipping name kana
                if(!$kanaName){
                    $errorList[] = "File = ".$this->_filePathName." External Order ID: ".$rakutenOrderNo." : 'The kana name must contain exactly one space separating first name and last name. Given = ".$csvOrderData[0]['配送先名カナ'];
//                    $this->tempoStarlogger->error("File = ".$this->_filePathName." External Order ID: ".$rakutenOrderNo." : 'The kana name must contain exactly one space separating first name and last name.");
                }

                $shipAddress = $this->helperData->separateAddress($csvOrderData[0]['注文者住所']); // Shipping address
                if(isset($shipAddress['street'])){
                    $shippingAddress = [
                        'firstname' => $kanjiName['first_name'],
                        'lastname' => $kanjiName['last_name'],
                        'firstname_kana' => $kanaName['first_name'],
                        'lastname_kana' => $kanaName['last_name'],
                        'street' => $shipAddress['street'],
                        'city' => $shipAddress['city'],
                        'region' => $shipAddress['region'],
                        'region_id' => $regionId,
                        'postcode' => $csvOrderData[0]['配送先郵便番号'], // Shipping postal code
                        'country_id' => 'JP',
                        'telephone' => $csvOrderData[0]['配送先電話番号'], // Telephone number
                        'save_in_address_book' => 0
                    ];
                }else{
                    $errorList[] = "File = ".$this->_filePathName." External Order ID: ".$rakutenOrderNo." : The shipping address must contain exactly two spaces separating region, city, and street.";
                }

                $items=[];
                foreach ($csvOrderData as $csvRowData) {
                    $colorSize = $this->helperData->getColorSize($csvRowData['項目選択肢']);
                    if(!isset($colorSize['カラー']) and !isset($colorSize['サイズ'])){
                        $errorList[] = "File = ".$this->_filePathName." External Order ID: ".$rakutenOrderNo." : Color or Size not set.";;
                    }

                    $items[] =
                        [
                            'sku' => $csvRowData['商品コード'], // Product code
                            'qty' => $csvRowData['数量'], // quantity
                            'color' => $colorSize['カラー'], // color
                            'size' => $colorSize['サイズ'], // size
                            'price' => $csvRowData['税抜単価'], // Unit price excluding tax
                            'price_incl_tax' => $csvRowData['税込単価'], // Unit price including tax
                            'row_total' => $csvRowData['税抜金額'], // Amount excluding tax
                            'tax_amount' => $csvRowData['消費税'], // consumption tax
                            'row_total_incl_tax' => $csvRowData['税込金額'], // Amount including tax
                            'points_per_delivery' => $csvRowData['配送毎ポイント'], // Points per delivery
                            'coupon_per_delivery' => $csvRowData['配送毎クーポン'], // Coupon per delivery
                            'product_name' => $csvRowData['商品名'], // Product name
                        ];

                }

                if(!empty($errorList)){
                    $this->tempoStarlogger->info(print_r($errorList,true));
                    continue;
                }

                $orderIncrementId = $this->createOrder($rakutenOrderNo, $email,
                    $items, $billingAddress, $shippingAddress, $orderData);
                if(!$orderIncrementId){
                    $this->tempoStarlogger->info("File = ".$this->_filePathName. " Can't create order External Order ID: ".$rakutenOrderNo);
                }
            }
        }
        return true;
    }

    /**
     * Ref: TO-6
     */
    public function updateOrder($csvData, $file)
    {
        foreach ($csvData as $rakutenOrderNo => $csvOrderData) {
            $orderProgressCode = $csvOrderData[0]['受注進捗コード']; // Order progress code
            // start: render warehouse comment, expected arrival date, requested arrival date, expected arrival timeslot, instructed timeslot
            $warehouseComment = $this->getWarehouseCommentText($csvOrderData[0]['受注備考']); //warehouse comment
            $customerSelectedDate = $csvOrderData[0]['配送日'];
            $customerSelectedTime = $csvOrderData[0]['配送時間名'];
            if (!empty($customerSelectedDate)) {
                $customerSelectedDate = date('Y-m-d', strtotime($customerSelectedDate));
            }
            if (!empty($customerSelectedTime)) {
                $customerSelectedTime = str_replace('-','～',$customerSelectedTime);
            }

            $tpsCustomerComment = $this->getCommentAdditionalShippingInstruction($csvOrderData[0]['受注備考']); // additional shipping instruction from comment
            // end
            $validCode = [600];
            if( !in_array($orderProgressCode, $validCode )){
                continue;
            }

            if (empty($csvOrderData[0]['注文者メールアドレス'])) continue;

            // Check is order exist by Rakuten external_order_id
            $this->order = $this->helperData->getOrderByExternalOrderId($rakutenOrderNo);
            if (!empty($this->order)) {
                // If order exist update order status
                $this->setOrderStatus($orderProgressCode);
                // update shipping instructions
                if (!empty($warehouseComment)) {
                    $this->order->setData('rakuten_order_remarks', $warehouseComment);
                }
                if (!empty($customerSelectedDate)) {
                    $this->order->setData('expected_arrival_date', $customerSelectedDate);
                    $this->order->setData('requested_arrival_date', $customerSelectedDate);
                }
                if (!empty($customerSelectedTime)) {
                    $this->order->setData('expected_arrival_timeslot', $customerSelectedTime);
                    $this->order->setData('instructed_timeslot', $customerSelectedTime);
                }
                if (!empty($tpsCustomerComment)) {
                    // first get external order data and only update json key
                    $externalOrderData = $this->order->getExternalOrderAdditionalInformation();
                    if (!empty($externalOrderData)) {
                        $externalOrderData = json_decode($externalOrderData, true);
                    }
                    $externalOrderData['tpsCustomerComment'] = $tpsCustomerComment;
                    $this->order->setExternalOrderAdditionalInformation(json_encode($externalOrderData, JSON_UNESCAPED_UNICODE));
                }
                $this->orderRepository->save($this->order);
                $this->tempoStarlogger->info("File = ".$this->_filePathName. " order update successfully External Order ID: ".$rakutenOrderNo. " IncrementId: ".$this->order->getIncrementId());
            }
        }
    }

    public function createOrder($rakutenOrderNo, $email,
                                $items, $billingAddress, $shippingAddress, $orderData)
    {
        try {
            // Create a new quote
            $quote = $this->cartFactory->create();
            $quote->setStore($this->storeManager->getStore());
            // Set the currency to Japanese Yen (JPY)
            $quote->setCurrency()->setBaseCurrencyCode('JPY')
                ->setQuoteCurrencyCode('JPY');
            $quote->setCustomerEmail($email);
            $quote->setCustomerIsGuest(true);
            $quote->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
            $quote->setCheckoutMethod(\Magento\Quote\Model\QuoteManagement::METHOD_GUEST);
            $colorAttrId = $this->productAttributeRepository->get('color')->getAttributeId();
            $sizeAttrId = $this->productAttributeRepository->get('size')->getAttributeId();
            $extraAdditionalInformation = [];
            $extraAdditionalInformation['配送方法コード'] = $orderData["shippingMethodCode"];
            $extraAdditionalInformation['システム受注番号'] = $orderData["systemOrderNumber"];
            $extraAdditionalInformation['tpsCustomerComment'] = $orderData['tpsCustomerComment'];
            foreach ($items as $item) {
                $product = $this->productRepository->get($item['sku']);
                // Check is product exist.
                if ($product->getId()) {
                    $colorOptionId = $this->getAttrOptIdByLabel('color', trim($item['color']));
                    $sizeOptionId = $this->getAttrOptIdByLabel('size', trim($item['size']));
                    $attributes = array($colorAttrId => $colorOptionId, $sizeAttrId => $sizeOptionId);
                    $requestInfo = new \Magento\Framework\DataObject(
                        [
                            'product_id' => $product->getId(),
                            'qty' => $item['qty'],
                            'custom_price' =>  $item['price_incl_tax'], // Unit price including tax
                        ]
                    );
                    try {
                        $quote->addProduct($product, $requestInfo);
                    } catch (\Exception $e) {
                        $this->tempoStarlogger->error("File = ".$this->_filePathName." External Order ID: ".$rakutenOrderNo." SKU: ".$item['sku']." : ".$e->getMessage());
//                        throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
                        return false;
                    }
                }

                //Set product item level point and coupon info
                $extraAdditionalInformation[$item['sku']] = [
                    '配送毎ポイント' => $item['points_per_delivery'],
                    '配送毎クーポン' => $item['coupon_per_delivery'],
                    '商品名' => $item['product_name'],
                ];
            }

            // Set billing and shipping addresses
            $quote->getBillingAddress()->addData($billingAddress);
            $quote->getShippingAddress()->addData($shippingAddress);

            // Collect totals
            $quote->collectTotals();
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setCollectShippingRates(true)->collectShippingRates()
                ->setShippingMethod('flatrate_flatrate');
//            $quote->setCreatedAt($orderData["orderCreatedAt"]);
            $quote->setInventoryProcessed(true);
            //Setting flag for stop sending order email
            $quote->setStopSendEmail(true);
            // Save quote
            $quote->save();

            // Set Sales Order Payment
            $quotePaymentObj = $quote->getPayment();
            $getPaymentName = $this->helperData->mapPaymentMethod($orderData["tempoPaymentCode"]);
            if ($orderData["tempoPaymentCode"] == '02' || $orderData["tempoPaymentCode"] == '2' || $orderData["tempoPaymentCode"] == '34') {
                $quotePaymentObj->setMethod('cashondelivery');
            } else {
                $quotePaymentObj->setMethod('purchaseorder');
            }
            $quotePaymentObj->setPoNumber($orderData["tempoPaymentCode"] . '-' . $getPaymentName);
            $quotePaymentObj->setAdditionalData($orderData["tempoPaymentCode"] . '-' . $getPaymentName);
            $quote->setPayment($quotePaymentObj);

            // Set COD fee in Quote
            if ($orderData["tempoPaymentCode"] == '02' || $orderData["tempoPaymentCode"] == '2' || $orderData["tempoPaymentCode"] == '34') {
                $quote->setCodFee($orderData["codFee"]); // Add COD fee to quote
            }

            // Collect Totals & Save Quote
            $quote->collectTotals()->save();

            // Convert to order
            $quote->setStoreId($this->helperData->getStoreID());
            $this->order = $this->cartManagement->submit($quote);

            //Setting flag for stop sending invoice email
            $this->order->setStopSendEmail(true);

            // set Order status
            $this->setOrderStatus($orderData["orderProgressCode"]);


            // set others order data
            $this->order->setOrderType("rakuten");
            $this->order->setData('external_order_id', $rakutenOrderNo);
            $this->order->setCreatedAt($this->helperData->getAdminTimezoneDatetime($orderData["orderCreatedAt"]));
            $this->order->setData('rakuten_order_remarks',$orderData['warehouseComment']);
            $this->order->setData('expected_arrival_date',$orderData['expectedArrivalDate']);
            $this->order->setData('requested_arrival_date',$orderData['instructedArrivalDate']);
            $this->order->setData('expected_arrival_timeslot',$orderData['expectedArrivalTimeslot']);
            $this->order->setData('instructed_timeslot',$orderData['instructedTimeslot']);

            $jsonExtraAdditionalInformation = json_encode($extraAdditionalInformation, JSON_UNESCAPED_UNICODE);
            $this->order
                ->setExternalOrderAdditionalInformation($jsonExtraAdditionalInformation);
            // $this->order->setCustomerIsGuest(true);
            // $this->order->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);

            // Get the shipping address
            $shipAddress = $this->order->getShippingAddress();
            if ($shipAddress) {
                // Set the first_nama_kana attribute
                $shipAddress->setData('first_nama_kana', $shippingAddress['firstname_kana']);
                $shipAddress->setData('last_nama_kana', $shippingAddress['firstname_kana']);
                // Save the shipping address
                $shipAddress->save();
            }

            // Get the billing address
            $billAddress = $this->order->getBillingAddress();
            if ($billAddress) {
                // Set the first_nama_kana attribute
                $billAddress->setData('first_nama_kana', $billingAddress['firstname_kana']);
                $billAddress->setData('last_nama_kana', $billingAddress['lastname_kana']);
                // Save the shipping address
                $billAddress->save();
            }

            // Set COD fee in order
            if ($orderData["tempoPaymentCode"] == '02' || $orderData["tempoPaymentCode"] == '2' || $orderData["tempoPaymentCode"] == '34') {
                $this->order->setCodFee($orderData["codFee"]); // Add COD fee to order
                $this->order->setBaseCodFee($orderData["codFee"]);
            }else{
                $orderData["codFee"] =0;
            }
            // save discount information
            $totalDiscount = 0;
            $couponDiscount = 0;
            $rewardDiscount = 0;

            // check for discount
            if ($couponDiscount = $orderData['couponDiscount']) {
                $this->order->setDiscountAmount(-$couponDiscount);
                $this->order->setBaseDiscountAmount(-$couponDiscount);
            }

            // check for reward points discount
            if ($rewardDiscount = $orderData['rewardDiscount']) {
                $this->order->setRewardCurrencyAmount($rewardDiscount);
                $this->order->setBaseRewardCurrencyAmount($rewardDiscount);
            }
            $totalDiscount = $couponDiscount + $rewardDiscount;
            // Set shipping fee amount.
            $this->order->setShippingAmount($orderData["shippingFee"]);
            $this->order->setBaseShippingAmount($orderData["shippingFee"]);
            $this->order->setGrandTotal($this->order->getGrandTotal()
                + $orderData["shippingFee"] + $orderData["codFee"] - $totalDiscount);

            $this->orderRepository->save($this->order);
            $this->helperData->customInventoryManage($this->order);
            $this->tempoStarlogger->info("File = ".$this->_filePathName. " order created successfully External Order ID: ".$rakutenOrderNo. " IncrementId: ".$this->order->getIncrementId());
            return true;

        } catch (\Exception $e) {
            $this->tempoStarlogger->error("File = ".$this->_filePathName." External Order ID: ".$rakutenOrderNo." : ".$e->getMessage());
            return false;
//            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * @param $orderProgressCode
     * @return void
     */
    private function setOrderStatus($orderProgressCode)
    {
        // Set the order status
        if ($orderProgressCode == 100 || $orderProgressCode == 200 ) {
            $this->order->setState(\Magento\Sales\Model\Order::STATE_NEW);
            $this->order->setStatus('pending');
            $this->order
                ->addStatusToHistory($this->order->getStatus(), 'Order imported successfully through TEMPOSTAR');
        } elseif ($orderProgressCode == 600) {
            $this->order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
            $this->order->setStatus('processing');
            $this->order
                ->addStatusToHistory($this->order->getStatus(), 'Order is updated through TEMPOSTAR');
        }
    }

    /**
     * @param $attrCode
     * @param $optLabel
     * @return string
     */
    public function getAttrOptIdByLabel($attrCode, $optLabel)
    {
        $product = $this->productFactory->create();
        $isAttrExist = $product->getResource()->getAttribute($attrCode);
        $optId = '';
        if ($isAttrExist && $isAttrExist->usesSource()) {
            $optId = $isAttrExist->getSource()->getOptionId($optLabel);
        }
        return $optId;
    }

    public function jdbg($label, $obj)
    {
        $fileName = strtolower(str_replace('\\', '_', get_class($this))) . '.log';
        // $fileName =’jdebug.log';
        $filePath = BP . '/var/log/debug_' . $fileName;
        $writer = new \Zend_Log_Writer_Stream($filePath);
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logStr = "{$label}:";
        switch (gettype($obj)) {
            case 'boolean':
                if ($obj) {
                    $logStr .= "(bool) -> TRUE";
                } else {
                    $logStr .= "(bool) -> FALSE";
                }
                break;
            case 'integer':
            case 'double':
            case 'string':
                $logStr .= "(" . gettype($obj) . ") -> {$obj}";
                break;
            case 'array':
                $logStr .= "(array) -> " . print_r($obj, true);
                break;
            case 'object':
                try {
                    if (method_exists($obj, 'debug')) {
                        $logStr .= "(" . get_class($obj) . ") -> " . print_r($obj->debug(), true);
                    } else {
                        $this->jdbg($label,print_r($obj,true));
                        $logStr .= "NULL";
                        break;
                        $logStr .= "Don't know how to log object of class " . get_class($obj);
                    }
                } catch (Exception $e) {
                    $logStr .= "Don't know how to log object of class " . get_class($obj);
                }
                break;
            case 'NULL':
                $logStr .= "NULL";
                break;
            default:
                $logStr .= "Don't know how to log type " . gettype($obj);
        }

        $logger->info($logStr);
    }

    public function getCommentAdditionalShippingInstruction($data)
    {
        $additionalShippingInstruction = '';
        if (!empty($data)) {
            // Extract additional text (text between time and comment)
            if (preg_match('/\[配送日時指定:\]\s*\d{4}-\d{2}-\d{2}\(.+?\)\s*(?:\d{2}:\d{2}-\d{2}:\d{2}\s*)?(.+?)\s*\[備考欄:/', str_replace(["\n", "\r"], ' ', $data), $matches)) {
                $additionalShippingInstruction = trim($matches[1]);
            }
            // remove timeslot (午前中) from customer comment
            $additionalShippingInstruction = str_replace('午前中', '',$additionalShippingInstruction);
        }
        return $additionalShippingInstruction;
    }

    public function getWarehouseCommentText($data)
    {
        $whComment = '';
        if (!empty($data)) {
            // Extract whComment (everything after "[備考欄:]" until the end)
            if (preg_match('/\[備考欄:\]([\s\S]+)/', $data, $matches)) {
                $whComment = trim($matches[1]);
            }
        }
        return $whComment;
    }

}

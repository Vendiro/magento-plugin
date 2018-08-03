<?php

class Vendiro_ApiHandler_Model_Order extends Mage_Core_Model_Abstract
{

    const DEFAULT_SHIPPING_METHOD = 'vendiro_standard';
    const DEFAULT_PAYMENT_METHOD = 'vendiro_standard';

    /**
     * @var Vendiro_ApiHandler_Helper_Data
     */
    protected $helper;

    /**
     * @var 
     */
    protected $apiData;

    /**
     * @var
     */
    protected $existingOrders;

    /**
     * @throws Exception
     */
    public function _construct()
    {
        parent::_construct();

        $this->helper = Mage::helper('vendiro_apihandler');
    }

    /**
     * @return void
     * @see https://api.vendiro.nl/docs/#accept-order
     */
    public function importOrderFromApi()
    {

        try {
            $this->apiData = $this->helper->getApiData();

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $this->apiData['url'] . '/client/orders?order_status=new&include_addresses=true',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => [
                    "Accept: application/json",
                    "Authorization: Basic " . base64_encode($this->apiData['key'].':'.$this->apiData['token']),
                    "Cache-Control: no-cache",
                    "Content-Type: application/json;"
                ],
            ]);

            $response = curl_exec($curl);
            $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                throw new Exception('Error by getting order data from Vendiro API: '.$err);
            }

            if ($httpStatus != 200) {
                throw new Exception('Error by getting order data from Vendiro API: wrong http status ' . $httpStatus . ' it should be 200');
            }

            $orders = json_decode($response);
            $this->importOrders($orders);

        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * @param $orders
     * @return bool
     * @throws Mage_Core_Exception
     */
    public function importOrders($orders)
    {
        if(empty($orders)) {
            return false;
        }

        if(!$this->existingOrders) {
            $this->existingOrders = $this->getExistingOrdersVendiroId();
        }

        foreach ($orders as $order) {
            try {
                if(isset($this->existingOrders[$order->marketplace_order_id])) {
                    return false;
                }

                $store = Mage::getModel('core/store')->load($order->marketplace->reference, 'code');

                if(!$store) {
                    throw new Exception('Not able to find store with the storecode ' . $order->marketplace->reference);
                }

                /** @var Mage_Sales_Model_Quote $quote */
                $quote = Mage::getModel('sales/quote')->setStoreId($store->getId());

                $this->addOrderLinesToQuote($quote, $order->orderlines);

                $this->addCustomerAddressToQuote($quote, $order, $store);

                $quote->getPayment()->importData(array('method' => self::DEFAULT_PAYMENT_METHOD));

                $quote->save();

                /** @var Mage_Sales_Model_Service_Quote $service */
                $service = Mage::getModel('sales/service_quote', $quote);
                $service->submitAll();
                $quote->setIsActive(false)->save();
                $_order = $service->getOrder()->save();

                $shippingAmount = $order->shipping_cost + $order->administration_cost;
                $_order->setShippingAmount($shippingAmount);
                $_order->setBaseShippingAmount($shippingAmount);

                $_order->setBaseGrandTotal($order->order_value);
                $_order->setGrandTotal($order->order_value);

                $orderStatus = $order->fulfilment_by_marketplace == 'true' ?  'complete' : 'processing';

                $orderComment = Mage::helper('vendiro_apihandler')->__(
                    'Order via Vendiro,<br> Marketplace: %s,<br>%s ID: %s',
                    $order->marketplace->name,
                    $order->marketplace->name,
                    $order->marketplace_order_id
                );
                $_order->addStatusHistoryComment($orderComment, $orderStatus);
                $_order->setVendiroId($order->id)
                    ->save();

                $this->createInvoice($_order);

                unset($quote);
                unset($service);

                $this->acceptOrderToApi($order->id, $_order->getIncrementId());

            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
    }

    /**
     * @param $order
     * @throws Exception
     */
    public function createInvoice($order)
    {
        if(!$order) {
            throw new Exception('Empty order, not able to create invoice for order');
        }

        try {
            if(!$order->canInvoice())
            {
                throw new Exception('Not able to create an invoice for order');
            }

            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

            if (!$invoice->getTotalQty()) {
                Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
            }

            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());

            $transactionSave->save();
        }
        catch (Mage_Core_Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @return array
     */
    public function getExistingOrdersVendiroId()
    {
        $orderCollection = Mage::getModel('sales/order')
            ->getCollection()
            ->addAttributeToSelect('vendiro_id')
            ->addAttributeToFilter('vendiro_id', array('notnull' => true));

        $existingOrders = array();

        foreach($orderCollection->getItems() as $order) {
            $existingOrders[$order->getVendiroId()] = $order->getVendiroId();
        }

        return $existingOrders;
    }

    /**
     * @param $quote
     * @param $order
     * @param $store
     * @throws Exception
     */
    public function addCustomerAddressToQuote(&$quote, $order, $store)
    {
        if(!$quote || !$order || !$store) {
            throw new Exception('Not able to add customer address to the quote');
        }

        $shippingAddress = (array) $order->delivery_address;
        $billingAddress = (array) $order->invoice_address;

        $quote->setCustomerEmail($billingAddress['email'])
            ->setCustomerFirstname($billingAddress['name'])
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);


        $quoteShippingAddress = new Mage_Sales_Model_Quote_Address();
        $quoteShippingAddress->setData($this->convertAddress($shippingAddress));

        $quoteBillingAddress = new Mage_Sales_Model_Quote_Address();
        $quoteBillingAddress->setData($this->convertAddress($billingAddress));

        $quote->setShippingAddress($quoteShippingAddress);
        $quote->setBillingAddress($quoteBillingAddress);

        $quote->getShippingAddress()
            ->setShippingMethod(self::DEFAULT_SHIPPING_METHOD)
            ->setPaymentMethod(self::DEFAULT_PAYMENT_METHOD)
            ->setCollectShippingRates(true)
            ->collectTotals();
    }

    /**
     * @param $quote
     * @param $orderlines
     * @throws Exception
     */
    public function addOrderLinesToQuote(&$quote, $orderlines)
    {
        if(!$quote || !$orderlines) {
            throw new Exception('Not able to add order lines to the quote');
        }

        foreach ($orderlines as $product) {
            /** @var Mage_Catalog_Model_Product $_product */
            $_catalog = Mage::getModel('catalog/product');
            $_productId = $_catalog->getIdBySku($product->sku);

            if (!$_productId) {
                throw new Exception('Product with sku: ' . $product->sku .' does not exist in magento');
            }

            $_product = Mage::getModel('catalog/product')->load($_productId);
            $price = $product->value;
            $total = $product->value_total;
            $weight = ($_product->getWeight() * $product->amount);
            $buyRequest = new Varien_Object(array('qty' => $product->amount));
            $quote->addProduct($_product, $buyRequest)->setOriginalCustomPrice($price);
        }
    }

    /**
     * @param $address
     * @return array
     */
    public function convertAddress($address)
    {
        $address = array_filter(array_map('trim',$address));

        $replaceKeys = array(
            "name"  =>  "firstname",
            "postalcode"    =>  "postcode",
            "country"   =>  "country_id",
            "phone" =>  "telephone"
        );

        foreach ($address as $key => $value) {
            if(isset($replaceKeys[$key])) {
                if(!$value) {
                    continue;
                }

                $address[$replaceKeys[$key]] = $value;
                unset($address[$key]);
            }
        }

        $address['lastname'] = '&nbsp;';
        $address['telephone'] = isset($address['telephone']) ? $address['telephone'] : '-';
        $address['region'] = '';
        $address['region_id'] = '';
        return $address;
    }


    /**
     * @param $apiOrderId
     * @param $magentoOrderId
     * @return bool
     */
    public function acceptOrderToApi($apiOrderId, $magentoOrderId)
    {
        try {
            $curl = curl_init();
            $dataJson = json_encode(array('order_ref' => $magentoOrderId));

            curl_setopt_array($curl, [
                CURLOPT_URL => $this->apiData['url'] . '/client/orders/' . $apiOrderId . '/accept',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_HTTPHEADER => [
                    "Accept: application/json",
                    "Authorization: Basic " . base64_encode($this->apiData['key'].':'.$this->apiData['token']),
                    "Cache-Control: no-cache",
                    "Content-Type: application/json;",
                    "Content-Length: " . strlen($dataJson)
                ],
            ]);

            curl_setopt($curl, CURLOPT_POSTFIELDS, $dataJson);

            $response = curl_exec($curl);
            $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                throw new Exception('Error by pushing order data to Vendiro API order accept: '.$err);
            }

            if ($httpStatus != 201 &&  $httpStatus != 422) {
                throw new Exception('Error by pushing order data to Vendiro API order accept: wrong http status ' . $httpStatus . ' it should be 201 or 422');
            }

            return true;

        } catch (Exception $e) {
            Mage::logException($e);
        }
    }
}
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
                    "Content-Type: application/json;",
                    "User-Agent: VendiroMagentoPlugin/" . $this->helper->getModuleVersion()
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
                if(!$order || !is_object($order) || isset($this->existingOrders[$order->id])) {
                    return false;
                }

                $store = Mage::getModel('core/store')->load($order->marketplace->reference, 'code');

                if(!$store->getId()) {
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

                $this->addShippingAmountToOrder($_order, $order, $store);

                $_order->setBaseGrandTotal($order->order_value);
                $_order->setGrandTotal($order->order_value);

                $_order->setBaseCurrencyCode($order->currency);
                $_order->setGlobalCurrencyCode($order->currency);
                $_order->setStoreCurrencyCode($order->currency);
                $_order->setOrderCurrencyCode($order->currency);

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

                $_order->setData('state', $orderStatus);
                $_order->save();

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
     * @param $_order
     * @param $order
     * @param $store
     * @throws Exception
     */
    public function addShippingAmountToOrder(&$_order, $order, $store)
    {
        if(!$_order || !$order || !$store) {
            throw new Exception('Not able to add shipping amount to the order');
        }

        $shippingAmount = $order->shipping_cost + $order->administration_cost;
        $taxId = Mage::getStoreConfig('tax/classes/shipping_tax_class', $store->getId());
        $percent = $this->getTax($_order->getShippingAddress(), $_order->getBillingAddress(), $store, $taxId);
        $shippingPriceCal = round(($shippingAmount / (100 + $percent) * 100), 2);
        $shippingPriceTaxCal = round($shippingAmount - $shippingPriceCal, 2);
        $orderTaxAmount = round($_order->getTaxAmount()+$shippingPriceTaxCal, 2);

        $_order->setBaseTaxAmount($orderTaxAmount);
        $_order->setTaxAmount($orderTaxAmount);
        $_order->setBaseShippingTaxAmount($shippingPriceTaxCal);
        $_order->setShippingTaxAmount($shippingPriceTaxCal);
        $_order->setShippingAmount($shippingPriceCal);
        $_order->setBaseShippingAmount($shippingPriceCal);
        $_order->setBaseShippingInclTax($shippingAmount);
        $_order->setShippingInclTax($shippingAmount);
    }

    /**
     * @param $shippingAddress
     * @param $billingAddress
     * @param $store
     * @param $taxId
     * @return mixed
     */
    public function getTax($shippingAddress, $billingAddress, $store, $taxId)
    {
        $taxCalculation = Mage::getSingleton('tax/calculation');
        $request = $taxCalculation->getRateRequest($shippingAddress, $billingAddress, null, $store);
        return $taxCalculation->getRate($request->setProductClassId($taxId));
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
            "name2"  =>  "company",
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

        if (isset($address['street2'])) {
            $address['street'] = $address['street'] . "\n" . $address['street2'];
        }

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
                    "Content-Length: " . strlen($dataJson),
                    "User-Agent: VendiroMagentoPlugin/" . $this->helper->getModuleVersion()
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

            if ($httpStatus != 204 &&  $httpStatus != 422) {
                throw new Exception('Error by pushing order data to Vendiro API order accept: wrong http status ' . $httpStatus . ' it should be 204 or 422');
            }

            return true;

        } catch (Exception $e) {
            Mage::logException($e);
        }
    }
}
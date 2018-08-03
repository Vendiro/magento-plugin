<?php

/**
 * Class Vendiro_ApiHandler_Model_Observer
 */
class Vendiro_ApiHandler_Model_Observer
{

    /** @var Vendiro_ApiHandler_Model_Stock */
    protected $modelStock;

    /** @var Vendiro_ApiHandler_Model_Order */
    protected $modelOrder;

    /** @var Vendiro_ApiHandler_Model_Tracking */
    protected $modelTracking;

    /**
     * Vendiro_ApiHandler_Model_Observer constructor.
     */
    public function __construct()
    {
        $this->modelStock = Mage::getSingleton('vendiro_apihandler/stock');
        $this->modelOrder = Mage::getSingleton('vendiro_apihandler/order');
        $this->modelTracking = Mage::getSingleton('vendiro_apihandler/tracking');
    }

    /**
     * @param Varien_Event_Observer $observer
     * @event cataloginventory_stock_item_save_commit_after
     */
    public function catalogInventorySave(Varien_Event_Observer $observer)
    {
        if ($this->isEnabled()) {
            $event = $observer->getEvent();
            $item = $event->getItem();

            if ((int)$item->getData('qty') != (int)$item->getOrigData('qty')) {
                $this->modelStock->addProductToQueue([$item->getProductId()]);
            }
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     * @event sales_model_service_quote_submit_before
     */
    public function subtractQuoteInventory(Varien_Event_Observer $observer)
    {
        if ($this->isEnabled()) {
            $productIds = [];
            $quote = $observer->getEvent()->getQuote();
            foreach ($quote->getAllItems() as $item) {
                $productIds[] = $item->getProductId();
            }

            $this->modelStock->addProductToQueue($productIds);
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     * @event sales_model_service_quote_submit_failure
     */
    public function revertQuoteInventory(Varien_Event_Observer $observer)
    {
        if ($this->isEnabled()) {
            $productIds = [];
            $quote = $observer->getEvent()->getQuote();
            foreach ($quote->getAllItems() as $item) {
                $productIds[] = $item->getProductId();
            }

            $this->modelStock->addProductToQueue($productIds);
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     * @event sales_order_item_cancel
     */
    public function cancelOrderItem(Varien_Event_Observer $observer)
    {
        if ($this->isEnabled()) {
            $item = $observer->getEvent()->getItem();
            $this->modelStock->addProductToQueue([$item->getProductId()]);
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     * @event sales_order_creditmemo_save_after
     */
    public function refundOrderInventory(Varien_Event_Observer $observer)
    {
        if ($this->isEnabled()) {
            $productIds = [];
            $creditmemo = $observer->getEvent()->getCreditmemo();
            foreach ($creditmemo->getAllItems() as $item) {
                $productIds[] = $item->getProductId();
            }

            $this->modelStock->addProductToQueue($productIds);
        }
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        /** @var Vendiro_ApiHandler_Helper_Data $helper */
        $helper = Mage::helper('vendiro_apihandler');
        return $helper->isEnabled();
    }

    /**
     * Send stock to API
     */
    public function sendStockToApi()
    {
        $this->modelStock->sendStockToApi();
    }

    /**
     * Send full stock to API
     */
    public function sendStockToApiFull()
    {
        $this->modelStock->sendStockToApi(true);
    }

    /**
     * Import Order from API
     */
    public function importOrderFromApi()
    {
        $this->modelOrder->importOrderFromApi();
    }

    /**
     * Export Shipment to API
     */
    public function exportShipmentToApi()
    {
        $this->modelTracking->exportShipmentToApi();
    }

}
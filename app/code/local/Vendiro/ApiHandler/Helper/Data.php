<?php

/**
 * Class Vendiro_ApiHandler_Helper_Data
 */
class Vendiro_ApiHandler_Helper_Data extends Mage_Core_Helper_Abstract
{

    protected $carriers;

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return Mage::getStoreConfigFlag('vendiro_apihandler/api/is_enabled');
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getApiData()
    {
        $data = [
            'url' => (string) Mage::getStoreConfig('vendiro_apihandler/api/api_url'),
            'key' => (string) Mage::getStoreConfig('vendiro_apihandler/api/api_key'),
            'token' => (string) Mage::getStoreConfig('vendiro_apihandler/api/api_token')
        ];

        if (empty($data['url']) || empty($data['key']) || empty($data['token'])) {
            throw new Exception('Vendiro API credentials are not set.');
        }

        return $data;
    }

    /**
     * @param $magentoShippingMethod
     * @return mixed
     * @throws Exception
     */
    public function getVendiroCarrierFromMagentoShippingMethod($magentoShippingMethod)
    {
        if (!$magentoShippingMethod) {
            throw new Exception('Missing Magento shipping method.');
        }

        if (!$carriers = $this->getVendiroCarriers()) {
            return $magentoShippingMethod;
        }

        if (isset($carriers[$magentoShippingMethod])) {
            return $carriers[$magentoShippingMethod];
        }

        return $magentoShippingMethod;
    }

    /**
     * @return array
     */
    public function getVendiroCarriers()
    {
        if (!$this->carriers) {
            $vendiroCarrierSettings = unserialize(Mage::getStoreConfig('vendiro_apihandler/shipping_methods/carriers'));
            $newCarriers = array();
            foreach ($vendiroCarrierSettings as $carrierSetting) {
                $newCarriers[$carrierSetting['shipping_method']] = $carrierSetting['shipping_carriers'];
            }

            $this->carriers = $newCarriers;
        }

        return $this->carriers;
    }
}
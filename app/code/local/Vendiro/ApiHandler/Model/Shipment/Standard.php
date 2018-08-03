<?php

/**
 * Class Vendiro_ApiHandler_Model_Shipment_Standard
 */
class Vendiro_ApiHandler_Model_Shipment_Standard extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = 'vendiro_standard';
    protected $_isFixed = true;

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return array(
            'standard'    =>  'Vendiro standard'
        );
    }

    /**
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return bool|Mage_Shipping_Model_Rate_Result|null
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        /** @var Mage_Shipping_Model_Rate_Result $result */
        $result = Mage::getModel('shipping/rate_result');

        $result->append($this->_getStandardRate());

        return $result;
    }

    /**
     * @return Mage_Shipping_Model_Rate_Result_Method
     */
    protected function _getStandardRate()
    {
        /** @var Mage_Shipping_Model_Rate_Result_Method $rate */
        $rate = Mage::getModel('shipping/rate_result_method');

        $rate->setCarrier('vendiro');
        $rate->setCarrierTitle('Vendiro');
        $rate->setMethod('standard');
        $rate->setMethodTitle('standard');
        $rate->setPrice(0);
        $rate->setCost(0);

        return $rate;
    }
}
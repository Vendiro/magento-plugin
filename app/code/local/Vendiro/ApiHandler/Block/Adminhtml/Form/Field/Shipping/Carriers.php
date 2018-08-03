<?php

/**
 * Class Vendiro_ApiHandler_Block_Adminhtml_Form_Field_Shipping_Carriers
 */
class Vendiro_ApiHandler_Block_Adminhtml_Form_Field_Shipping_Carriers extends Mage_Core_Block_Html_Select
{

    /**
     * @param $value
     * @return mixed
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * @return string
     */
    public function _toHtml()
    {
        if (!$this->getOptions()) {
            /** @var Vendiro_ApiHandler_Model_Tracking $model */
            $model = Mage::getSingleton('vendiro_apihandler/tracking');
            $carriersFromApi = $model->getCarriersFromApi();

            foreach ($carriersFromApi as $code => $carrierTitle) {
                $this->addOption($code, addslashes($carrierTitle));
            }
        }
        return parent::_toHtml();
    }
}
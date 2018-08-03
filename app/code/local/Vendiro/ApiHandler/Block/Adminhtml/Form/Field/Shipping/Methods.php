<?php

/**
 * Class Vendiro_ApiHandler_Block_Adminhtml_Form_Field_Shipping_Methods
 */
class Vendiro_ApiHandler_Block_Adminhtml_Form_Field_Shipping_Methods extends Mage_Core_Block_Html_Select
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
            $methods = Mage::getSingleton('shipping/config')->getAllCarriers();

            foreach ($methods as $shippingCode => $shippingModel)
            {
                if ($shippingCode == Vendiro_ApiHandler_Model_Order::DEFAULT_SHIPPING_METHOD) {
                    continue;
                }

                $shippingTitle = Mage::getStoreConfig('carriers/'.$shippingCode.'/title');
                $this->addOption($shippingCode, addslashes($shippingTitle));
            }
        }
        return parent::_toHtml();
    }
}
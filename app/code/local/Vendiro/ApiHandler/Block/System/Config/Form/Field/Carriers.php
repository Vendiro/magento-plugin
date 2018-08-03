<?php

/**
 * Class Vendiro_ApiHandler_Block_System_Config_Form_Field_Carriers
 */
class Vendiro_ApiHandler_Block_System_Config_Form_Field_Carriers extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{

    /**
     * @var
     */
    protected $shippingmethods;

    /**
     * @var
     */
    protected $shippingcarriers;

    /**
     * @return Mage_Core_Block_Abstract
     */
    protected function _getAllShippingMethods()
    {
        if (!$this->shippingmethods) {
            $this->shippingmethods = $this->getLayout()->createBlock(
                'vendiro_apihandler/adminhtml_form_field_shipping_methods', '',
                array('is_render_to_js_template' => true)
            );
            $this->shippingmethods->setClass('shipping_method');
            $this->shippingmethods->setExtraParams('style="width:120px"');
        }
        return $this->shippingmethods;
    }

    /**
     * @return Mage_Core_Block_Abstract
     */
    protected function _getApiShippingCarriers()
    {
        if (!$this->shippingcarriers) {
            $this->shippingcarriers = $this->getLayout()->createBlock(
                'vendiro_apihandler/adminhtml_form_field_shipping_carriers', '',
                array('is_render_to_js_template' => true)
            );
            $this->shippingcarriers->setClass('shipping_carriers');
            $this->shippingcarriers->setExtraParams('style="width:120px"');
        }
        return $this->shippingcarriers;
    }

    /**
     *
     */
    protected function _prepareToRender()
    {

        $this->addColumn('shipping_method', array(
            'label' => Mage::helper('customer')->__('Magento Shipping Method'),
            'renderer' => $this->_getAllShippingMethods(),
        ));

        $this->addColumn('shipping_carriers', array(
            'label' => Mage::helper('customer')->__('Vendiro carrier'),
            'renderer' => $this->_getApiShippingCarriers(),
        ));

        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('cataloginventory')->__('Add Shipping Method');
    }

    /**
     * @param Varien_Object $row
     */
    protected function _prepareArrayRow(Varien_Object $row)
    {
        $row->setData(
            'option_extra_attr_' . $this->_getAllShippingMethods()->calcOptionHash($row->getData('shipping_method')),
            'selected="selected"'
        );
        $row->setData(
            'option_extra_attr_' . $this->_getApiShippingCarriers()->calcOptionHash($row->getData('shipping_carriers')),
            'selected="selected"'
        );
    }
}
<?php

/**
 * Class Vendiro_ApiHandler_Block_System_Config_Form_Field_Button
 */
class Vendiro_ApiHandler_Block_System_Config_Form_Field_Button extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    /**
     * @var Mage_Adminhtml_Helper_Data
     */
    public $helper;

    /**
     * @inheritdoc.
     */
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('vendiro/apihandler/system/config/test_button.phtml');
        $this->helper = Mage::helper('adminhtml');
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return "<script type='text/javascript'>
    //<![CDATA[
    function testModule() {
        new Ajax.Request('" . $this->getAjaxCheckUrl() . "', {
            parameters: {isAjax: 1},
            method: 'get',
            onCreate: function () {
            },
            onFailure: function(transport) {
                alert(transport.responseText);
            },
            onSuccess: function (transport) {
                alert(transport.responseText);
            }
        });
    }

    //]]>
</script>" . $this->getButtonHtml();
    }

    /**
     * Return ajax url for button
     *
     * @return string
     */
    public function getAjaxCheckUrl()
    {
        return $this->helper->getUrl('adminhtml/selftest/run');
    }

    /**
     * Generate button html
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(
                array(
                    'id' => 'test_check_button',
                    'label' => $this->helper('adminhtml')->__('Test login'),
                    'onclick' => 'javascript:testModule(); return false;'
                )
            );
        return $button->toHtml();
    }
}
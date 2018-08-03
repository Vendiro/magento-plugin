<?php

class Vendiro_ApiHandler_Model_Resource_Stock extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('vendiro_apihandler/stock', 'product_id');
    }
}
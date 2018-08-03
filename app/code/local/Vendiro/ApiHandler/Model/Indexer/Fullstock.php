<?php

class Vendiro_ApiHandler_Model_Indexer_Fullstock extends Mage_Index_Model_Indexer_Abstract
{

    /**
     * @var Vendiro_ApiHandler_Helper_Data
     */
    protected $helper;

    /**
     * Initialize indexer
     */
    protected function _construct()
    {
        $this->helper = Mage::helper('vendiro_apihandler');
    }

    /**
     * Retrieve indexer name
     *
     * @return string
     */
    public function getName()
    {
        return $this->helper->__('Vendiro API Stock Updater');
    }

    /**
     * Retrieve indexer description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->helper->__('Update Stock in Vendiro');
    }

    /**
     * Update all stock in Vendiro
     */
    public function reindexAll()
    {
        /** @var Vendiro_ApiHandler_Model_Stock $modelStock */
        $modelStock = Mage::getSingleton('vendiro_apihandler/stock');
        $modelStock->updateAll();
    }

    /**
     * Register data required by process in event object
     *
     * @param Mage_Index_Model_Event $event
     */
    protected function _registerEvent(Mage_Index_Model_Event $event)
    {

    }

    /**
     * Process event
     *
     * @param Mage_Index_Model_Event $event
     */
    protected function _processEvent(Mage_Index_Model_Event $event)
    {
    }

    /**
     * @return bool
     */
    public function isVisible()
    {
        return $this->helper->isEnabled();
    }
}
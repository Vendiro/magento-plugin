<?php

/**
 * Class Vendiro_ApiHandler_Model_Adminhtml_System_Config_Backend_Crons_Stock_Partial
 */
class Vendiro_ApiHandler_Model_Adminhtml_System_Config_Backend_Crons_Stock_Partial extends Mage_Core_Model_Config_Data
{
    const CRON_STRING_PATH = 'crontab/jobs/vendiro_send_stock/schedule/cron_expr';

    /**
     * @return Mage_Core_Model_Abstract|void
     * @throws Exception
     */
    protected function _afterSave()
    {
        $frequency = $this->getData('groups/crons/fields/stock_partial_cron_expression/value');

        try {
            Mage::getModel('core/config_data')
                ->load(self::CRON_STRING_PATH, 'path')
                ->setValue($frequency)
                ->setPath(self::CRON_STRING_PATH)
                ->save();
        }
        catch (Exception $e) {
            throw new Exception(Mage::helper('cron')->__('Unable to save the cron expression.'));

        }
    }
}